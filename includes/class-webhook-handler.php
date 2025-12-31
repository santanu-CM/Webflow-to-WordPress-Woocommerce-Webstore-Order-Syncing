<?php
/**
 * Webhook handler.
 *
 * @package CapacityTShirtsStores
 * @subpackage Webhooks
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Webhooks;

use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Database\Repository\Log_Repository;
use CapacityTShirtsStores\Database\Repository\Order_Repository;
use CapacityTShirtsStores\Core\Logger;
use CapacityTShirtsStores\Core\Order_Normalizer;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook handler class.
 */
class Webhook_Handler {

    /**
     * Store repository instance.
     *
     * @var Store_Repository
     */
    private Store_Repository $store_repository;

    /**
     * Log repository instance.
     *
     * @var Log_Repository
     */
    private Log_Repository $log_repository;

    /**
     * Order repository instance.
     *
     * @var Order_Repository
     */
    private Order_Repository $order_repository;

    /**
     * Constructor.
     *
     * @param Store_Repository $store_repository Store repository.
     * @param Log_Repository $log_repository Log repository.
     * @param Order_Repository $order_repository Order repository.
     */
    public function __construct(Store_Repository $store_repository, Log_Repository $log_repository, Order_Repository $order_repository) {
        $this->store_repository = $store_repository;
        $this->log_repository = $log_repository;
        $this->order_repository = $order_repository;
    }

    /**
     * Register webhook routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route(
            'capacity-tshirts-stores/v1',
            '/webhook',
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => [$this, 'verify_webhook'],
            ]
        );
    }

    /**
     * Verify webhook request.
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool
     */
    public function verify_webhook(\WP_REST_Request $request): bool {
        // Basic verification - can be enhanced with signature verification.
        // For now, we'll accept all POST requests to the webhook endpoint.
        // In production, you should verify webhook signatures from Webflow.
        return true;
    }

    /**
     * Handle webhook callback.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_webhook(\WP_REST_Request $request) {
        $body = $request->get_json_params();
        $headers = $request->get_headers();

        // Extract webhook data.
        $event_type = $body['triggerType'] ?? $body['event_type'] ?? 'unknown';
        $site_id = $body['siteId'] ?? $body['site_id'] ?? null;
        $payload = $body;

        // Find store by identifier.
        $store = null;
        $stores = $this->store_repository->get_all();
        
        if ($site_id) {
            // Try to find store by site_id.
            foreach ($stores as $store_item) {
                if ($store_item['store_identifier'] === $site_id) {
                    $store = $store_item;
                    break;
                }
            }
        }
        
        // If store not found by site_id, try to find by webflow store type.
        // This is a fallback in case site_id is not in the payload.
        if (!$store && ($event_type === 'ecomm_new_order' || $event_type === 'ecomm_order_changed')) {
            // Try to find store by orderId in existing orders.
            if (!empty($payload['payload']['orderId'])) {
                $order_id = $payload['payload']['orderId'];
                $existing_order = $this->order_repository->get_by_platform_order_id('webflow', $order_id);
                if ($existing_order && !empty($existing_order['store_id'])) {
                    $store = $this->store_repository->get_by_id((int) $existing_order['store_id']);
                }
            }
            
            // If still not found, use the first Webflow store as fallback.
            if (!$store) {
                foreach ($stores as $store_item) {
                    if ($store_item['store_type'] === 'webflow' && !empty($store_item['store_identifier'])) {
                        // For now, use the first Webflow store found.
                        // In production, you might want to use webhook signature verification
                        // or store the site_id in the webhook URL path.
                        $store = $store_item;
                        break;
                    }
                }
            }
        }

        $store_id = $store ? absint($store['id']) : null;
        $store_type = $store ? $store['store_type'] : 'unknown';

        // Log webhook callback.
        Logger::log_webhook_callback($store_type, $event_type, $payload, $store_id);

        // Process webhook based on event type.
        $this->process_webhook($event_type, $payload, $store);

        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * Process webhook based on event type.
     *
     * @param string $event_type Event type.
     * @param array<string, mixed> $payload Webhook payload.
     * @param array<string, mixed>|null $store Store data.
     * @return void
     */
    private function process_webhook(string $event_type, array $payload, ?array $store): void {
        switch ($event_type) {
            case 'ecomm_new_order':
            case 'ecomm_order_changed':
                $this->process_order_webhook($payload, $store);
                break;

            case 'ecomm_inventory_changed':
                $this->process_inventory_webhook($payload, $store);
                break;

            default:
                // Unknown event type - just log it.
                break;
        }
    }

    /**
     * Process order webhook.
     *
     * @param array<string, mixed> $payload Webhook payload.
     * @param array<string, mixed>|null $store Store data.
     * @return void
     */
    private function process_order_webhook(array $payload, ?array $store): void {
        try {
            if (!$store) {
                Logger::log('Order webhook received but store not found', 'warning', [
                    'payload' => $payload,
                ]);
                return;
            }

            $store_id = absint($store['id']);
            $store_type = $store['store_type'] ?? '';

            // Only process Webflow orders for now.
            if ($store_type !== 'webflow') {
                Logger::log('Order webhook received for unsupported store type', 'warning', [
                    'store_type' => $store_type,
                    'store_id' => $store_id,
                ]);
                return;
            }

            // Normalize and store the order.
            $normalized_order = Order_Normalizer::normalize_webflow_order($payload, $store_id);

            // Check if order already exists to prevent duplicates.
            $existing_order = $this->order_repository->get_by_platform_order_id(
                $normalized_order['platform'],
                $normalized_order['platform_order_id']
            );

            if ($existing_order) {
                // Update existing order.
                $this->order_repository->update((int) $existing_order['id'], $normalized_order);
                Logger::log('Order updated from webhook', 'info', [
                    'order_id' => $existing_order['id'],
                    'platform_order_id' => $normalized_order['platform_order_id'],
                    'store_id' => $store_id,
                ], $store_id);
            } else {
                // Create new order.
                $order_id = $this->order_repository->create($normalized_order);
                if ($order_id) {
                    Logger::log('Order created from webhook', 'success', [
                        'order_id' => $order_id,
                        'platform_order_id' => $normalized_order['platform_order_id'],
                        'store_id' => $store_id,
                    ], $store_id);
                } else {
                    Logger::log('Failed to create order from webhook', 'error', [
                        'platform_order_id' => $normalized_order['platform_order_id'],
                        'store_id' => $store_id,
                    ], $store_id);
                }
            }
        } catch (\InvalidArgumentException $e) {
            Logger::log('Invalid order data in webhook', 'error', [
                'error' => $e->getMessage(),
                'store_id' => $store ? absint($store['id']) : null,
            ], $store ? absint($store['id']) : null);
        } catch (\Exception $e) {
            Logger::log('Error processing order webhook', 'error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'store_id' => $store ? absint($store['id']) : null,
            ], $store ? absint($store['id']) : null);
        }
    }

    /**
     * Process inventory webhook.
     *
     * @param array<string, mixed> $payload Webhook payload.
     * @param array<string, mixed>|null $store Store data.
     * @return void
     */
    private function process_inventory_webhook(array $payload, ?array $store): void {
        // Placeholder for inventory processing.
        // In a full implementation, you would:
        // 1. Parse inventory data from payload
        // 2. Update inventory records
        // 3. Trigger any necessary actions
    }
}


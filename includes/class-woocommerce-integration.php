<?php
/**
 * WooCommerce integration.
 *
 * @package CapacityTShirtsStores
 * @subpackage Integrations
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Integrations;

use CapacityTShirtsStores\Database\Repository\Order_Repository;
use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Core\Order_Normalizer;
use CapacityTShirtsStores\Core\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce integration class.
 *
 * Handles synchronization of WooCommerce orders to the plugin's order system.
 */
class WooCommerce_Integration {

    /**
     * Order repository instance.
     *
     * @var Order_Repository
     */
    private Order_Repository $order_repository;

    /**
     * Store repository instance.
     *
     * @var Store_Repository
     */
    private Store_Repository $store_repository;

    /**
     * Constructor.
     *
     * @param Order_Repository $order_repository Order repository.
     */
    public function __construct(Order_Repository $order_repository) {
        $this->order_repository = $order_repository;
        $this->store_repository = new Store_Repository();
    }

    /**
     * Get or create WooCommerce store (current WordPress site).
     *
     * @return int|null Store ID on success, null on failure.
     */
    private function get_woocommerce_store_id(): ?int {
        // Use site URL as the store identifier for WooCommerce.
        $store_identifier = home_url();
        $site_name = get_bloginfo('name') ?: 'WooCommerce Store';

        // Try to find existing WooCommerce store.
        $stores = $this->store_repository->get_all(['limit' => 0]);
        foreach ($stores as $store) {
            if ($store['store_type'] === 'woocommerce' && $store['store_identifier'] === $store_identifier) {
                return (int) $store['id'];
            }
        }

        // Create new WooCommerce store if not found.
        $store_id = $this->store_repository->create([
            'title' => $site_name,
            'store_type' => 'woocommerce',
            'store_identifier' => $store_identifier,
            'oauth_data' => null, // WooCommerce doesn't need OAuth (it's the current site).
            'webhook_status' => 'active', // WooCommerce orders are synced directly, no webhooks needed.
        ]);

        if ($store_id) {
            Logger::log('WooCommerce store created automatically', 'info', [
                'store_id' => $store_id,
                'store_identifier' => $store_identifier,
            ]);
            return $store_id;
        }

        return null;
    }

    /**
     * Sync WooCommerce order to plugin database.
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return int|false Order ID on success, false on failure.
     */
    public function sync_order(\WC_Order $wc_order): int|false {
        try {
            if (!$wc_order || !$wc_order->get_id()) {
                return false;
            }

            // Get or create WooCommerce store (current WordPress site).
            $store_id = $this->get_woocommerce_store_id();
            if (!$store_id) {
                Logger::log('Failed to get or create WooCommerce store', 'error', [
                    'wc_order_id' => $wc_order->get_id(),
                ]);
                // Continue without store_id if store creation fails.
            }

            // Normalize WooCommerce order data.
            $normalized_order = Order_Normalizer::normalize_woocommerce_order($wc_order, $store_id);

            // Check if order already exists to prevent duplicates.
            $existing_order = $this->order_repository->get_by_platform_order_id(
                $normalized_order['platform'],
                $normalized_order['platform_order_id']
            );

            if ($existing_order) {
                // Update existing order.
                $order_id = (int) $existing_order['id'];
                $this->order_repository->update($order_id, $normalized_order);
                Logger::log('WooCommerce order updated', 'info', [
                    'order_id' => $order_id,
                    'wc_order_id' => $wc_order->get_id(),
                    'store_id' => $store_id,
                ], $store_id);
                return $order_id;
            } else {
                // Create new order.
                $order_id = $this->order_repository->create($normalized_order);
                if ($order_id) {
                    Logger::log('WooCommerce order synced', 'success', [
                        'order_id' => $order_id,
                        'wc_order_id' => $wc_order->get_id(),
                        'store_id' => $store_id,
                    ], $store_id);
                    return $order_id;
                } else {
                    Logger::log('Failed to sync WooCommerce order', 'error', [
                        'wc_order_id' => $wc_order->get_id(),
                        'store_id' => $store_id,
                    ], $store_id);
                    return false;
                }
            }
        } catch (\InvalidArgumentException $e) {
            Logger::log('Invalid WooCommerce order data', 'error', [
                'error' => $e->getMessage(),
                'wc_order_id' => $wc_order ? $wc_order->get_id() : null,
            ]);
            return false;
        } catch (\Exception $e) {
            Logger::log('Error syncing WooCommerce order', 'error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'wc_order_id' => $wc_order ? $wc_order->get_id() : null,
            ]);
            return false;
        }
    }
}


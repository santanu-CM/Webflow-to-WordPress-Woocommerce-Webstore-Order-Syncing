<?php
/**
 * Order normalizer.
 *
 * @package CapacityTShirtsStores
 * @subpackage Core
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Core;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order normalizer class.
 *
 * Normalizes order data from different platforms (Webflow, WooCommerce, Shopify)
 * into a uniform format for storage and display.
 */
class Order_Normalizer {

    /**
     * Normalize Webflow order data.
     *
     * @param array<string, mixed> $webflow_order Webflow order payload.
     * @param int|null $store_id Store ID.
     * @return array<string, mixed> Normalized order data.
     * @throws \InvalidArgumentException If order data is invalid.
     */
    public static function normalize_webflow_order(array $webflow_order, ?int $store_id = null): array {
        if (empty($webflow_order['payload']) || empty($webflow_order['payload']['orderId'])) {
            throw new \InvalidArgumentException('Invalid Webflow order data: missing orderId');
        }

        $payload = $webflow_order['payload'];
        $customer_info = $payload['customerInfo'] ?? [];
        $totals = $payload['totals'] ?? [];
        $total = $totals['total'] ?? ['value' => 0, 'unit' => 'USD'];

        // Extract order number from orderId (format: "8d0-665" or similar).
        $order_id = sanitize_text_field($payload['orderId']);
        $order_number = $order_id;

        // Extract customer information.
        $customer_name = !empty($customer_info['fullName']) 
            ? sanitize_text_field($customer_info['fullName']) 
            : null;
        $customer_email = !empty($customer_info['email']) 
            ? sanitize_email($customer_info['email']) 
            : null;

        // Extract total amount.
        $total_amount = isset($total['value']) ? floatval($total['value']) : 0.00;
        $currency = isset($total['unit']) ? sanitize_text_field($total['unit']) : 'USD';

        // Extract order status.
        $status = !empty($payload['status']) 
            ? sanitize_text_field($payload['status']) 
            : 'pending';

        // Map Webflow status to standard statuses.
        $status = self::normalize_status($status, 'webflow');

        // Extract order date.
        $order_date = !empty($payload['acceptedOn']) 
            ? self::parse_webflow_date($payload['acceptedOn']) 
            : current_time('mysql');

        return [
            'store_id' => $store_id,
            'platform' => 'webflow',
            'platform_order_id' => $order_id,
            'order_number' => $order_number,
            'status' => $status,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'total_amount' => $total_amount,
            'currency' => $currency,
            'order_date' => $order_date,
            'order_data' => $webflow_order, // Store full payload for details page.
        ];
    }

    /**
     * Normalize WooCommerce order data.
     *
     * @param \WC_Order $wc_order WooCommerce order object.
     * @param int|null $store_id Store ID.
     * @return array<string, mixed> Normalized order data.
     * @throws \InvalidArgumentException If order is invalid.
     */
    public static function normalize_woocommerce_order(\WC_Order $wc_order, ?int $store_id = null): array {
        if (!$wc_order || !$wc_order->get_id()) {
            throw new \InvalidArgumentException('Invalid WooCommerce order');
        }

        $order_id = (string) $wc_order->get_id();
        $order_number = $wc_order->get_order_number();

        // Extract customer information.
        $customer_name = trim($wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name());
        if (empty($customer_name)) {
            $customer_name = $wc_order->get_formatted_billing_full_name();
        }
        $customer_email = $wc_order->get_billing_email();

        // Extract total amount.
        $total_amount = floatval($wc_order->get_total());
        $currency = $wc_order->get_currency();

        // Extract order status.
        $status = $wc_order->get_status();
        $status = self::normalize_status($status, 'woocommerce');

        // Extract order date.
        $order_date = $wc_order->get_date_created();
        $order_date = $order_date ? $order_date->date('Y-m-d H:i:s') : current_time('mysql');

        // Prepare order data for storage.
        $order_data = [
            'order_id' => $wc_order->get_id(),
            'order_key' => $wc_order->get_order_key(),
            'currency' => $currency,
            'payment_method' => $wc_order->get_payment_method(),
            'billing' => [
                'first_name' => $wc_order->get_billing_first_name(),
                'last_name' => $wc_order->get_billing_last_name(),
                'company' => $wc_order->get_billing_company(),
                'address_1' => $wc_order->get_billing_address_1(),
                'address_2' => $wc_order->get_billing_address_2(),
                'city' => $wc_order->get_billing_city(),
                'state' => $wc_order->get_billing_state(),
                'postcode' => $wc_order->get_billing_postcode(),
                'country' => $wc_order->get_billing_country(),
                'email' => $wc_order->get_billing_email(),
                'phone' => $wc_order->get_billing_phone(),
            ],
            'shipping' => [
                'first_name' => $wc_order->get_shipping_first_name(),
                'last_name' => $wc_order->get_shipping_last_name(),
                'company' => $wc_order->get_shipping_company(),
                'address_1' => $wc_order->get_shipping_address_1(),
                'address_2' => $wc_order->get_shipping_address_2(),
                'city' => $wc_order->get_shipping_city(),
                'state' => $wc_order->get_shipping_state(),
                'postcode' => $wc_order->get_shipping_postcode(),
                'country' => $wc_order->get_shipping_country(),
            ],
            'items' => [],
            'totals' => [
                'subtotal' => floatval($wc_order->get_subtotal()),
                'shipping' => floatval($wc_order->get_shipping_total()),
                'tax' => floatval($wc_order->get_total_tax()),
                'total' => floatval($wc_order->get_total()),
            ],
        ];

        // Extract order items.
        foreach ($wc_order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $order_data['items'][] = [
                'id' => $item_id,
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => floatval($item->get_total()),
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'sku' => $product ? $product->get_sku() : '',
            ];
        }

        return [
            'store_id' => $store_id,
            'platform' => 'woocommerce',
            'platform_order_id' => $order_id,
            'order_number' => $order_number,
            'status' => $status,
            'customer_name' => $customer_name ?: null,
            'customer_email' => $customer_email ?: null,
            'total_amount' => $total_amount,
            'currency' => $currency,
            'order_date' => $order_date,
            'order_data' => $order_data,
        ];
    }

    /**
     * Normalize Shopify order data (placeholder for future implementation).
     *
     * @param array<string, mixed> $shopify_order Shopify order data.
     * @param int|null $store_id Store ID.
     * @return array<string, mixed> Normalized order data.
     * @throws \InvalidArgumentException If order data is invalid.
     */
    public static function normalize_shopify_order(array $shopify_order, ?int $store_id = null): array {
        // Placeholder for future Shopify integration.
        // This will be implemented when Shopify integration is added.
        throw new \InvalidArgumentException('Shopify order normalization not yet implemented');
    }

    /**
     * Normalize order status from platform-specific to standard format.
     *
     * @param string $status Platform-specific status.
     * @param string $platform Platform name.
     * @return string Normalized status.
     */
    private static function normalize_status(string $status, string $platform): string {
        $status = strtolower(trim($status));

        // Webflow status mapping.
        if ($platform === 'webflow') {
            $status_map = [
                'unfulfilled' => 'pending',
                'fulfilled' => 'completed',
                'refunded' => 'refunded',
                'disputed' => 'disputed',
            ];
            return $status_map[$status] ?? 'pending';
        }

        // WooCommerce status mapping.
        if ($platform === 'woocommerce') {
            $status_map = [
                'pending' => 'pending',
                'processing' => 'processing',
                'on-hold' => 'on-hold',
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                'refunded' => 'refunded',
                'failed' => 'failed',
            ];
            return $status_map[$status] ?? 'pending';
        }

        return 'pending';
    }

    /**
     * Parse Webflow date string to MySQL datetime format.
     *
     * @param string $date_string Webflow date string (ISO 8601).
     * @return string MySQL datetime string.
     */
    private static function parse_webflow_date(string $date_string): string {
        try {
            $date = new \DateTime($date_string);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return current_time('mysql');
        }
    }
}


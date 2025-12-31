<?php
/**
 * Order repository.
 *
 * @package CapacityTShirtsStores
 * @subpackage Database\Repository
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Database\Repository;

use wpdb;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order repository class.
 */
class Order_Repository {

    /**
     * Table name.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'capacity_tshirts_orders';
    }

    /**
     * Get orders.
     *
     * @param array<string, mixed> $args Query arguments.
     * @return array<int, array<string, mixed>>
     */
    public function get_all(array $args = []): array {
        global $wpdb;

        $defaults = [
            'store_id' => null,
            'platform' => null,
            'status' => null,
            'search' => null,
            'orderby' => 'order_date',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $where_values = [];

        if ($args['store_id'] !== null) {
            $where[] = 'store_id = %d';
            $where_values[] = absint($args['store_id']);
        }

        if ($args['platform'] !== null && $args['platform'] !== '') {
            $where[] = 'platform = %s';
            $where_values[] = sanitize_text_field($args['platform']);
        }

        if ($args['status'] !== null && $args['status'] !== '') {
            $where[] = 'status = %s';
            $where_values[] = sanitize_text_field($args['status']);
        }

        if ($args['search'] !== null && $args['search'] !== '') {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[] = '(order_number LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR platform_order_id LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'order_date DESC';
        }

        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        // Build cache key for query results (cache for 5 minutes).
        $cache_key = 'cts_orders_' . md5(serialize($args) . $limit . $offset);
        $cached_results = get_transient($cache_key);
        
        if ($cached_results !== false) {
            return $cached_results;
        }

        // Only select needed columns for better performance.
        $sql = "SELECT id, store_id, platform, platform_order_id, order_number, status, customer_name, customer_email, total_amount, currency, order_date, created_at, updated_at FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, ...array_merge($where_values, [$limit, $offset]));
        } else {
            $sql = $wpdb->prepare($sql, $limit, $offset);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        $results = is_array($results) ? $results : [];
        
        // Cache results for 5 minutes.
        set_transient($cache_key, $results, 5 * MINUTE_IN_SECONDS);

        return $results;
    }

    /**
     * Get order by ID.
     *
     * @param int $id Order ID.
     * @return array<string, mixed>|null
     */
    public function get_by_id(int $id): ?array {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Get order by platform and platform order ID.
     *
     * @param string $platform Platform name (webflow, woocommerce, shopify).
     * @param string $platform_order_id Platform-specific order ID.
     * @return array<string, mixed>|null
     */
    public function get_by_platform_order_id(string $platform, string $platform_order_id): ?array {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE platform = %s AND platform_order_id = %s",
                sanitize_text_field($platform),
                sanitize_text_field($platform_order_id)
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    /**
     * Create an order.
     *
     * @param array<string, mixed> $data Order data.
     * @return int|false Order ID on success, false on failure.
     */
    public function create(array $data): int|false {
        global $wpdb;

        $defaults = [
            'store_id' => null,
            'platform' => '',
            'platform_order_id' => '',
            'order_number' => null,
            'status' => 'pending',
            'customer_name' => null,
            'customer_email' => null,
            'total_amount' => 0.00,
            'currency' => 'USD',
            'order_date' => current_time('mysql'),
            'order_data' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Sanitize data.
        $data['platform'] = sanitize_text_field($data['platform']);
        $data['platform_order_id'] = sanitize_text_field($data['platform_order_id']);
        $data['status'] = sanitize_text_field($data['status']);
        $data['currency'] = sanitize_text_field($data['currency']);

        if ($data['store_id'] !== null) {
            $data['store_id'] = absint($data['store_id']);
        }

        if ($data['order_number'] !== null) {
            $data['order_number'] = sanitize_text_field($data['order_number']);
        }

        if ($data['customer_name'] !== null) {
            $data['customer_name'] = sanitize_text_field($data['customer_name']);
        }

        if ($data['customer_email'] !== null) {
            $data['customer_email'] = sanitize_email($data['customer_email']);
        }

        $data['total_amount'] = floatval($data['total_amount']);

        if (is_array($data['order_data'])) {
            $data['order_data'] = wp_json_encode($data['order_data']);
        }

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        // Clear order caches when new order is created.
        $this->clear_order_caches();

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an order.
     *
     * @param int $id Order ID.
     * @param array<string, mixed> $data Order data.
     * @return bool True on success, false on failure.
     */
    public function update(int $id, array $data): bool {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        // Sanitize data if provided.
        if (isset($data['platform'])) {
            $data['platform'] = sanitize_text_field($data['platform']);
        }
        if (isset($data['platform_order_id'])) {
            $data['platform_order_id'] = sanitize_text_field($data['platform_order_id']);
        }
        if (isset($data['status'])) {
            $data['status'] = sanitize_text_field($data['status']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = sanitize_text_field($data['currency']);
        }
        if (isset($data['order_number'])) {
            $data['order_number'] = sanitize_text_field($data['order_number']);
        }
        if (isset($data['customer_name'])) {
            $data['customer_name'] = sanitize_text_field($data['customer_name']);
        }
        if (isset($data['customer_email'])) {
            $data['customer_email'] = sanitize_email($data['customer_email']);
        }
        if (isset($data['total_amount'])) {
            $data['total_amount'] = floatval($data['total_amount']);
        }
        if (isset($data['order_data']) && is_array($data['order_data'])) {
            $data['order_data'] = wp_json_encode($data['order_data']);
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $id],
            null,
            ['%d']
        );

        if ($result !== false) {
            // Clear order caches when order is updated.
            $this->clear_order_caches();
        }

        return $result !== false;
    }

    /**
     * Count orders.
     *
     * @param array<string, mixed> $args Query arguments.
     * @return int
     */
    public function count(array $args = []): int {
        global $wpdb;

        $defaults = [
            'store_id' => null,
            'platform' => null,
            'status' => null,
            'search' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $where_values = [];

        if ($args['store_id'] !== null) {
            $where[] = 'store_id = %d';
            $where_values[] = absint($args['store_id']);
        }

        if ($args['platform'] !== null && $args['platform'] !== '') {
            $where[] = 'platform = %s';
            $where_values[] = sanitize_text_field($args['platform']);
        }

        if ($args['status'] !== null && $args['status'] !== '') {
            $where[] = 'status = %s';
            $where_values[] = sanitize_text_field($args['status']);
        }

        if ($args['search'] !== null && $args['search'] !== '') {
            $search_term = sanitize_text_field($args['search']);
            $search = '%' . $wpdb->esc_like($search_term) . '%';
            // Optimize search: use exact match for order_number and platform_order_id first (faster)
            if (is_numeric($search_term)) {
                $where[] = '(order_number = %s OR platform_order_id = %s OR order_number LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR platform_order_id LIKE %s)';
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search;
                $where_values[] = $search;
                $where_values[] = $search;
                $where_values[] = $search;
            } else {
                $where[] = '(order_number LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR platform_order_id LIKE %s)';
                $where_values[] = $search;
                $where_values[] = $search;
                $where_values[] = $search;
                $where_values[] = $search;
            }
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Cache count queries for 5 minutes.
        $cache_key = 'cts_orders_count_' . md5(serialize($args));
        $cached_count = get_transient($cache_key);
        
        if ($cached_count !== false) {
            return (int) $cached_count;
        }

        if (!empty($where_values)) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} {$where_clause}", ...$where_values);
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        }

        $count = (int) $wpdb->get_var($sql);
        
        // Cache count for 5 minutes.
        set_transient($cache_key, $count, 5 * MINUTE_IN_SECONDS);

        return $count;
    }

    /**
     * Delete an order.
     *
     * @param int $id Order ID.
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );

        if ($result !== false) {
            // Clear order caches when order is deleted.
            $this->clear_order_caches();
        }

        return $result !== false;
    }

    /**
     * Clear order-related caches.
     *
     * @return void
     */
    private function clear_order_caches(): void {
        global $wpdb;
        
        // Delete all transients that start with our cache prefix.
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_cts_orders_%' 
            OR option_name LIKE '_transient_timeout_cts_orders_%'"
        );
    }
}


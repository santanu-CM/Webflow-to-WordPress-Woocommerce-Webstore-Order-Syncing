<?php
/**
 * Store repository.
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
 * Store repository class.
 */
class Store_Repository {

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
        $this->table_name = $wpdb->prefix . 'capacity_tshirts_stores';
    }

    /**
     * Get all stores.
     *
     * @param array<string, mixed> $args Query arguments.
     * @return array<int, array<string, mixed>>
     */
    public function get_all(array $args = []): array {
        global $wpdb;

        $defaults = [
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }

        $sql = "SELECT * FROM {$this->table_name}";

        if ($args['limit'] > 0) {
            $limit = absint($args['limit']);
            $offset = absint($args['offset']);
            $sql .= $wpdb->prepare(" ORDER BY {$orderby} LIMIT %d OFFSET %d", $limit, $offset);
        } else {
            $sql .= " ORDER BY {$orderby}";
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Get store by ID.
     *
     * @param int $id Store ID.
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
     * Create a store.
     *
     * @param array<string, mixed> $data Store data.
     * @return int|false Store ID on success, false on failure.
     */
    public function create(array $data): int|false {
        global $wpdb;

        $defaults = [
            'title' => '',
            'store_type' => '',
            'store_identifier' => null,
            'oauth_data' => null,
            'webhook_status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Sanitize data.
        $data['title'] = sanitize_text_field($data['title']);
        $data['store_type'] = sanitize_text_field($data['store_type']);
        $data['store_identifier'] = $data['store_identifier'] ? sanitize_text_field($data['store_identifier']) : null;
        $data['webhook_status'] = sanitize_text_field($data['webhook_status']);

        if (is_array($data['oauth_data'])) {
            $data['oauth_data'] = wp_json_encode($data['oauth_data']);
        }

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        // Clear store caches when new store is created.
        $this->clear_store_caches();

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a store.
     *
     * @param int $id Store ID.
     * @param array<string, mixed> $data Store data.
     * @return bool
     */
    public function update(int $id, array $data): bool {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        // Sanitize data.
        if (isset($data['title'])) {
            $data['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['store_type'])) {
            $data['store_type'] = sanitize_text_field($data['store_type']);
        }
        if (isset($data['store_identifier'])) {
            $data['store_identifier'] = sanitize_text_field($data['store_identifier']);
        }
        if (isset($data['webhook_status'])) {
            $data['webhook_status'] = sanitize_text_field($data['webhook_status']);
        }
        if (isset($data['oauth_data']) && is_array($data['oauth_data'])) {
            $data['oauth_data'] = wp_json_encode($data['oauth_data']);
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $id],
            null,
            ['%d']
        );

        if ($result !== false) {
            // Clear store caches when store is updated.
            $this->clear_store_caches();
        }

        return $result !== false;
    }

    /**
     * Delete a store.
     *
     * @param int $id Store ID.
     * @return bool
     */
    public function delete(int $id): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );

        if ($result !== false) {
            // Clear store caches when store is deleted.
            $this->clear_store_caches();
        }

        return $result !== false;
    }

    /**
     * Count stores.
     *
     * @return int
     */
    public function count(): int {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        return (int) $count;
    }

    /**
     * Clear store-related caches.
     *
     * @return void
     */
    private function clear_store_caches(): void {
        global $wpdb;
        
        // Delete all transients that start with our cache prefix.
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_cts_stores_%' 
            OR option_name LIKE '_transient_timeout_cts_stores_%'"
        );
    }
}


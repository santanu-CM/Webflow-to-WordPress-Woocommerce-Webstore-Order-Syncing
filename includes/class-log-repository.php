<?php
/**
 * Log repository.
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
 * Log repository class.
 */
class Log_Repository {

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
        $this->table_name = $wpdb->prefix . 'capacity_tshirts_logs';
    }

    /**
     * Get logs.
     *
     * @param array<string, mixed> $args Query arguments.
     * @return array<int, array<string, mixed>>
     */
    public function get_all(array $args = []): array {
        global $wpdb;

        $defaults = [
            'store_id' => null,
            'event_type' => null,
            'status' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $where_values = [];

        if ($args['store_id'] !== null) {
            $where[] = 'store_id = %d';
            $where_values[] = absint($args['store_id']);
        }

        if ($args['event_type'] !== null) {
            $where[] = 'event_type = %s';
            $where_values[] = sanitize_text_field($args['event_type']);
        }

        if ($args['status'] !== null) {
            $where[] = 'status = %s';
            $where_values[] = sanitize_text_field($args['status']);
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }

        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        $sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, ...array_merge($where_values, [$limit, $offset]));
        } else {
            $sql = $wpdb->prepare($sql, $limit, $offset);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * Get log by ID.
     *
     * @param int $id Log ID.
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
     * Check for duplicate log entry within a time window.
     *
     * @param string $message Log message.
     * @param int|null $store_id Store ID.
     * @param string $event_type Event type.
     * @param int $seconds Time window in seconds (default: 2).
     * @return bool True if duplicate exists, false otherwise.
     */
    public function check_duplicate(string $message, ?int $store_id, string $event_type, int $seconds = 2): bool {
        global $wpdb;

        // Calculate the time threshold using WordPress time functions
        $time_threshold = date('Y-m-d H:i:s', current_time('timestamp') - $seconds);

        // Use JSON_UNQUOTE(JSON_EXTRACT()) to get exact message match from payload JSON
        // JSON_UNQUOTE removes the JSON quotes so we can compare the actual string value
        // This is more accurate than LIKE which can match partial strings
        $where = [
            'event_type = %s',
            "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message')) = %s",
            'created_at >= %s',
        ];
        $where_values = [
            sanitize_text_field($event_type),
            sanitize_text_field($message),
            $time_threshold,
        ];

        if ($store_id !== null) {
            $where[] = 'store_id = %d';
            $where_values[] = absint($store_id);
        } else {
            $where[] = 'store_id IS NULL';
        }

        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode(' AND ', $where);
        $count = (int) $wpdb->get_var($wpdb->prepare($query, ...$where_values));

        return $count > 0;
    }

    /**
     * Create a log entry.
     *
     * @param array<string, mixed> $data Log data.
     * @return int|false Log ID on success, false on failure.
     */
    public function create(array $data): int|false {
        global $wpdb;

        $defaults = [
            'store_id' => null,
            'event_type' => '',
            'payload' => null,
            'status' => 'info',
            'created_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Sanitize data.
        $data['event_type'] = sanitize_text_field($data['event_type']);
        $data['status'] = sanitize_text_field($data['status']);

        if ($data['store_id'] !== null) {
            $data['store_id'] = absint($data['store_id']);
        }

        if (is_array($data['payload'])) {
            $data['payload'] = wp_json_encode($data['payload']);
        }

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            ['%d', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Count logs.
     *
     * @param array<string, mixed> $args Query arguments.
     * @return int
     */
    public function count(array $args = []): int {
        global $wpdb;

        $defaults = [
            'store_id' => null,
            'event_type' => null,
            'status' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $where_values = [];

        if ($args['store_id'] !== null) {
            $where[] = 'store_id = %d';
            $where_values[] = absint($args['store_id']);
        }

        if ($args['event_type'] !== null) {
            $where[] = 'event_type = %s';
            $where_values[] = sanitize_text_field($args['event_type']);
        }

        if ($args['status'] !== null) {
            $where[] = 'status = %s';
            $where_values[] = sanitize_text_field($args['status']);
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        if (!empty($where_values)) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} {$where_clause}", ...$where_values);
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        }

        $count = $wpdb->get_var($sql);

        return (int) $count;
    }

    /**
     * Delete old logs.
     *
     * @param int $days Number of days to keep.
     * @return int|false Number of rows deleted, false on failure.
     */
    public function delete_old(int $days = 90): int|false {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $date
            )
        );

        return $result;
    }
}


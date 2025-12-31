<?php
/**
 * Database schema handler.
 *
 * @package CapacityTShirtsStores
 * @subpackage Database
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Database;

use wpdb;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schema class.
 */
class Schema {

    /**
     * Create database tables.
     *
     * @return void
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . 'capacity_tshirts_';

        // Stores table.
        $stores_table = $table_prefix . 'stores';
        $stores_sql = "CREATE TABLE IF NOT EXISTS {$stores_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            store_type varchar(50) NOT NULL,
            store_identifier varchar(255) DEFAULT NULL,
            oauth_data longtext DEFAULT NULL,
            webhook_status varchar(50) DEFAULT 'pending',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY store_type (store_type),
            KEY store_identifier (store_identifier)
        ) {$charset_collate};";

        // Logs table.
        $logs_table = $table_prefix . 'logs';
        $logs_sql = "CREATE TABLE IF NOT EXISTS {$logs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            store_id bigint(20) UNSIGNED DEFAULT NULL,
            event_type varchar(100) NOT NULL,
            payload longtext DEFAULT NULL,
            status varchar(50) DEFAULT 'info',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY store_id (store_id),
            KEY event_type (event_type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        // Orders table.
        $orders_table = $table_prefix . 'orders';
        $orders_sql = "CREATE TABLE IF NOT EXISTS {$orders_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            store_id bigint(20) UNSIGNED DEFAULT NULL,
            platform varchar(50) NOT NULL,
            platform_order_id varchar(255) NOT NULL,
            order_number varchar(255) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            customer_name varchar(255) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            total_amount decimal(10,2) DEFAULT 0.00,
            currency varchar(10) DEFAULT 'USD',
            order_date datetime NOT NULL,
            order_data longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_platform_order (platform, platform_order_id),
            KEY store_id (store_id),
            KEY platform (platform),
            KEY status (status),
            KEY order_date (order_date),
            KEY customer_email (customer_email),
            KEY idx_store_platform_status (store_id, platform, status),
            KEY idx_platform_status_date (platform, status, order_date),
            KEY idx_store_status_date (store_id, status, order_date),
            KEY idx_order_date_desc (order_date DESC)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($stores_sql);
        dbDelta($logs_sql);
        dbDelta($orders_sql);
    }

    /**
     * Drop database tables.
     *
     * @return void
     */
    public static function drop_tables(): void {
        global $wpdb;

        $table_prefix = $wpdb->prefix . 'capacity_tshirts_';

        $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}orders");
        $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}logs");
        $wpdb->query("DROP TABLE IF EXISTS {$table_prefix}stores");
    }
}


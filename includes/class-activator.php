<?php
/**
 * Plugin activation handler.
 *
 * @package CapacityTShirtsStores
 * @subpackage Core
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Core;

use CapacityTShirtsStores\Database\Schema;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activator class.
 */
class Activator {

    /**
     * Activate the plugin.
     *
     * @return void
     */
    public static function activate(): void {
        // Create database tables.
        Schema::create_tables();

        // Set default options.
        add_option('capacity_tshirts_stores_version', CAPACITY_TSHIRTS_STORES_VERSION);

        // Flush rewrite rules.
        flush_rewrite_rules();
    }
}


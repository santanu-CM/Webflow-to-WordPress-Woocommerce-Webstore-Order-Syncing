<?php
/**
 * Plugin deactivation handler.
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
 * Deactivator class.
 */
class Deactivator {

    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public static function deactivate(): void {
        // Flush rewrite rules.
        flush_rewrite_rules();
    }
}


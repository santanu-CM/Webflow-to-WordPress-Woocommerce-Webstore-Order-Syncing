<?php
/**
 * Assets handler.
 *
 * @package CapacityTShirtsStores
 * @subpackage Admin
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Admin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets class.
 */
class Assets {

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue(string $hook): void {
        // Only load on our plugin pages.
        if (strpos($hook, 'capacity-tshirts-stores') === false) {
            return;
        }

        // Enqueue CSS.
        wp_enqueue_style(
            'capacity-tshirts-stores-admin',
            CAPACITY_TSHIRTS_STORES_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CAPACITY_TSHIRTS_STORES_VERSION
        );

        // Enqueue JavaScript.
        wp_enqueue_script(
            'capacity-tshirts-stores-admin',
            CAPACITY_TSHIRTS_STORES_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            CAPACITY_TSHIRTS_STORES_VERSION,
            true
        );

        // Localize script.
        wp_localize_script(
            'capacity-tshirts-stores-admin',
            'capacityTShirtsStores',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('capacity_tshirts_stores_nonce'),
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to delete this store?', 'capacity-tshirts-stores'),
                ],
            ]
        );
    }
}


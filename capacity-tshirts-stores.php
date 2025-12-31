<?php
/**
 * Plugin Name: Capacity T-Shirts Stores
 * Plugin URI: https://capcitytshirts.com
 * Description: Manage multiple e-commerce store integrations (Webflow, Shopify) with OAuth and webhook support.
 * Version: 1.0.0
 * Author: Capacity
 * Author URI: https://capcitytshirts.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: capacity-tshirts-stores
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 8.3
 *
 * @package CapacityTShirtsStores
 */

declare(strict_types=1);

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('CAPACITY_TSHIRTS_STORES_VERSION', '1.0.0');
define('CAPACITY_TSHIRTS_STORES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CAPACITY_TSHIRTS_STORES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CAPACITY_TSHIRTS_STORES_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader.
if (file_exists(CAPACITY_TSHIRTS_STORES_PLUGIN_DIR . 'includes/class-autoloader.php')) {
    require_once CAPACITY_TSHIRTS_STORES_PLUGIN_DIR . 'includes/class-autoloader.php';
} else {
    wp_die('Capacity T-Shirts Stores plugin: Autoloader file not found.');
}

// Initialize the plugin.
add_action('plugins_loaded', function (): void {
    if (class_exists('\CapacityTShirtsStores\Core\Plugin')) {
        \CapacityTShirtsStores\Core\Plugin::get_instance()->init();
    }
}, 10);

// Activation hook.
register_activation_hook(__FILE__, function (): void {
    // Ensure autoloader has loaded classes.
    if (class_exists('\CapacityTShirtsStores\Core\Activator')) {
        \CapacityTShirtsStores\Core\Activator::activate();
    }
});

// Deactivation hook.
register_deactivation_hook(__FILE__, function (): void {
    // Ensure autoloader has loaded classes.
    if (class_exists('\CapacityTShirtsStores\Core\Deactivator')) {
        \CapacityTShirtsStores\Core\Deactivator::deactivate();
    }
});


<?php
/**
 * Autoloader for the plugin.
 *
 * @package CapacityTShirtsStores
 */

declare(strict_types=1);

namespace CapacityTShirtsStores;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autoloader class.
 */
class Autoloader {

    /**
     * Register the autoloader.
     *
     * @return void
     */
    public static function register(): void {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload classes.
     *
     * @param string $class_name The class name to load.
     * @return void
     */
    public static function autoload(string $class_name): void {
        // Only handle our namespace.
        if (strpos($class_name, 'CapacityTShirtsStores\\') !== 0) {
            return;
        }

        // Remove namespace prefix.
        $class_name = str_replace('CapacityTShirtsStores\\', '', $class_name);

        // Extract the final class name (after last namespace separator).
        $parts = explode('\\', $class_name);
        $final_class_name = end($parts);

        // Convert class name to file name.
        // Handle both underscore and camelCase naming.
        $file_name = 'class-' . str_replace('_', '-', strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $final_class_name))) . '.php';

        // Build file path.
        // All classes are in includes directory (no subdirectories for namespace parts).
        // This handles: Core, Admin, Database\Repository, Integrations, Webhooks, etc.
        $file_path = CAPACITY_TSHIRTS_STORES_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $file_name;

        // Load the file if it exists.
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }

        // If file not found, try alternative paths for debugging.
        // This helps identify if there's a naming mismatch.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Capacity T-Shirts Stores Autoloader: Class "%s" not found. Tried: %s',
                $class_name,
                $file_path
            ));
        }
    }
}

// Register the autoloader.
Autoloader::register();


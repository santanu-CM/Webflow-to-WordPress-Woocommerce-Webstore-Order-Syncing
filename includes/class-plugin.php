<?php
/**
 * Main plugin class.
 *
 * @package CapacityTShirtsStores
 * @subpackage Core
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Core;

use CapacityTShirtsStores\Admin\Admin_Menu;
use CapacityTShirtsStores\Admin\Assets;
use CapacityTShirtsStores\Admin\Ajax_Handler;
use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Database\Repository\Log_Repository;
use CapacityTShirtsStores\Database\Repository\Order_Repository;
use CapacityTShirtsStores\Webhooks\Webhook_Handler;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class.
 */
class Plugin {

    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Store repository instance.
     *
     * @var Store_Repository|null
     */
    private ?Store_Repository $store_repository = null;

    /**
     * Log repository instance.
     *
     * @var Log_Repository|null
     */
    private ?Log_Repository $log_repository = null;

    /**
     * Order repository instance.
     *
     * @var Order_Repository|null
     */
    private ?Order_Repository $order_repository = null;

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function get_instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    public function init(): void {
        // Verify required classes exist.
        if (!class_exists('\CapacityTShirtsStores\Database\Repository\Store_Repository')) {
            add_action('admin_notices', function (): void {
                echo '<div class="notice notice-error"><p>Capacity T-Shirts Stores: Required classes not found. Please deactivate and reactivate the plugin.</p></div>';
            });
            return;
        }

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Admin hooks.
        if (is_admin()) {
            add_action('admin_menu', [$this, 'init_admin_menu'], 10);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets'], 10);
            
            // Debug: Add admin notice to verify plugin is loading (remove in production).
            add_action('admin_notices', [$this, 'debug_admin_notice'], 1);
        }

        // AJAX handlers.
        add_action('admin_init', [$this, 'init_ajax_handlers'], 10);

        // Webhook handler.
        add_action('rest_api_init', [$this, 'register_webhook_routes'], 10);

        // WooCommerce integration (if WooCommerce is active).
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_new_order', [$this, 'handle_woocommerce_order'], 10, 1);
            add_action('woocommerce_order_status_changed', [$this, 'handle_woocommerce_order'], 10, 1);
        }
    }

    /**
     * Initialize AJAX handlers.
     *
     * @return void
     */
    public function init_ajax_handlers(): void {
        $ajax_handler = new Ajax_Handler($this->get_store_repository(), $this->get_order_repository());
        $ajax_handler->register();
    }

    /**
     * Debug admin notice (temporary - remove in production).
     *
     * @return void
     */
    public function debug_admin_notice(): void {
        // Remove this method in production.
        // Uncomment below to see if plugin is loading:
        // echo '<div class="notice notice-info"><p>Capacity T-Shirts Stores plugin is loaded.</p></div>';
    }

    /**
     * Initialize admin menu.
     *
     * @return void
     */
    public function init_admin_menu(): void {
        // Check if user has permission.
        if (!current_user_can('manage_options')) {
            return;
        }

        // Ensure classes are loaded.
        if (!class_exists('\CapacityTShirtsStores\Admin\Admin_Menu')) {
            return;
        }

        $admin_menu = new Admin_Menu($this->get_store_repository(), $this->get_log_repository());
        $admin_menu->register();
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void {
        $assets = new Assets();
        $assets->enqueue($hook);
    }

    /**
     * Register webhook routes.
     *
     * @return void
     */
    public function register_webhook_routes(): void {
        $webhook_handler = new Webhook_Handler(
            $this->get_store_repository(),
            $this->get_log_repository(),
            $this->get_order_repository()
        );
        $webhook_handler->register_routes();
    }

    /**
     * Handle WooCommerce order.
     *
     * @param int $order_id WooCommerce order ID.
     * @return void
     */
    public function handle_woocommerce_order(int $order_id): void {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            return;
        }

        try {
            // Ensure WooCommerce_Integration class is loaded.
            if (!class_exists('\CapacityTShirtsStores\Integrations\WooCommerce_Integration')) {
                $integration_file = CAPACITY_TSHIRTS_STORES_PLUGIN_DIR . 'includes/class-woocommerce-integration.php';
                if (file_exists($integration_file)) {
                    require_once $integration_file;
                } else {
                    error_log('Capacity T-Shirts Stores: WooCommerce_Integration class file not found at: ' . $integration_file);
                    return;
                }
            }

            $integration = new \CapacityTShirtsStores\Integrations\WooCommerce_Integration($this->get_order_repository());
            $integration->sync_order($wc_order);
        } catch (\Exception $e) {
            // Log error but don't break WooCommerce order processing.
            error_log('Capacity T-Shirts Stores: Error syncing WooCommerce order: ' . $e->getMessage());
        }
    }

    /**
     * Get store repository.
     *
     * @return Store_Repository
     */
    public function get_store_repository(): Store_Repository {
        if ($this->store_repository === null) {
            $this->store_repository = new Store_Repository();
        }

        return $this->store_repository;
    }

    /**
     * Get log repository.
     *
     * @return Log_Repository
     */
    public function get_log_repository(): Log_Repository {
        if ($this->log_repository === null) {
            $this->log_repository = new Log_Repository();
        }

        return $this->log_repository;
    }

    /**
     * Get order repository.
     *
     * @return Order_Repository
     */
    public function get_order_repository(): Order_Repository {
        if ($this->order_repository === null) {
            $this->order_repository = new Order_Repository();
        }

        return $this->order_repository;
    }
}


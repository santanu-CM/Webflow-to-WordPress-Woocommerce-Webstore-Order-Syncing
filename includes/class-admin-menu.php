<?php
/**
 * Admin menu handler.
 *
 * @package CapacityTShirtsStores
 * @subpackage Admin
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Admin;

use CapacityTShirtsStores\Admin\Pages\Store_List_Page;
use CapacityTShirtsStores\Admin\Pages\Orders_Page;
use CapacityTShirtsStores\Admin\Pages\Log_History_Page;
use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Database\Repository\Log_Repository;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin menu class.
 */
class Admin_Menu {

    /**
     * Store repository instance.
     *
     * @var Store_Repository
     */
    private Store_Repository $store_repository;

    /**
     * Log repository instance.
     *
     * @var Log_Repository
     */
    private Log_Repository $log_repository;

    /**
     * Constructor.
     *
     * @param Store_Repository $store_repository Store repository.
     * @param Log_Repository $log_repository Log repository.
     */
    public function __construct(Store_Repository $store_repository, Log_Repository $log_repository) {
        $this->store_repository = $store_repository;
        $this->log_repository = $log_repository;
    }

    /**
     * Register admin menu.
     *
     * @return void
     */
    public function register(): void {
        // Verify we have the required dependencies.
        if (!$this->store_repository || !$this->log_repository) {
            return;
        }

        // Top-level menu.
        add_menu_page(
            __('Capacity T-Shirts Stores', 'capacity-tshirts-stores'),
            __('Stores', 'capacity-tshirts-stores'),
            'manage_options',
            'capacity-tshirts-stores',
            [$this, 'render_store_list_page'],
            'dashicons-store',
            30
        );

        // Store List submenu (same as parent).
        add_submenu_page(
            'capacity-tshirts-stores',
            __('Store List', 'capacity-tshirts-stores'),
            __('Store List', 'capacity-tshirts-stores'),
            'manage_options',
            'capacity-tshirts-stores',
            [$this, 'render_store_list_page']
        );

        // Orders submenu.
        add_submenu_page(
            'capacity-tshirts-stores',
            __('Orders', 'capacity-tshirts-stores'),
            __('Orders', 'capacity-tshirts-stores'),
            'manage_options',
            'capacity-tshirts-stores-orders',
            [$this, 'render_orders_page']
        );

        // Settings submenu.
        add_submenu_page(
            'capacity-tshirts-stores',
            __('Settings', 'capacity-tshirts-stores'),
            __('Settings', 'capacity-tshirts-stores'),
            'manage_options',
            'capacity-tshirts-stores-settings',
            [$this, 'render_settings_page']
        );

        // Activity Log submenu (displays Log Reports).
        add_submenu_page(
            'capacity-tshirts-stores',
            __('Activity Log', 'capacity-tshirts-stores'),
            __('Activity Log', 'capacity-tshirts-stores'),
            'manage_options',
            'capacity-tshirts-stores-activity-log',
            [$this, 'render_activity_log_page']
        );

        // OAuth callback page (hidden from menu).
        add_submenu_page(
            null,
            __('OAuth Callback', 'capacity-tshirts-stores'),
            __('OAuth Callback', 'capacity-tshirts-stores'),
            'manage_options',
            'capacity-tshirts-stores-oauth-callback',
            [$this, 'render_oauth_callback_page']
        );
    }

    /**
     * Render store list page.
     *
     * @return void
     */
    public function render_store_list_page(): void {
        $page = new Store_List_Page($this->store_repository, $this->log_repository);
        $page->render();
    }

    /**
     * Render orders page.
     *
     * @return void
     */
    public function render_orders_page(): void {
        $page = new Orders_Page($this->store_repository);
        $page->render();
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {
        // Handle settings form submission.
        if (isset($_POST['capacity_tshirts_stores_settings']) && check_admin_referer('capacity_tshirts_stores_settings')) {
            if (isset($_POST['webflow_client_id'])) {
                update_option('capacity_tshirts_webflow_client_id', sanitize_text_field($_POST['webflow_client_id']));
            }
            if (isset($_POST['webflow_client_secret'])) {
                update_option('capacity_tshirts_webflow_client_secret', sanitize_text_field($_POST['webflow_client_secret']));
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'capacity-tshirts-stores') . '</p></div>';
        }

        // Get current values (check constants first, then options).
        $webflow_client_id = defined('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID') 
            ? CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID 
            : get_option('capacity_tshirts_webflow_client_id', '');
        
        $webflow_client_secret = defined('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET') 
            ? CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET 
            : get_option('capacity_tshirts_webflow_client_secret', '');

        $has_constants = defined('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID') && defined('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Settings', 'capacity-tshirts-stores'); ?></h1>
            
            <?php if ($has_constants) : ?>
                <div class="notice notice-info">
                    <p><?php echo esc_html__('Webflow credentials are configured via wp-config.php constants. The values below are read-only.', 'capacity-tshirts-stores'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('capacity_tshirts_stores_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="webflow_client_id"><?php echo esc_html__('Webflow Client ID', 'capacity-tshirts-stores'); ?></label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="webflow_client_id" 
                                name="webflow_client_id" 
                                value="<?php echo esc_attr($webflow_client_id); ?>" 
                                class="regular-text" 
                                <?php echo $has_constants ? 'readonly' : ''; ?>
                            />
                            <p class="description">
                                <?php 
                                if ($has_constants) {
                                    echo esc_html__('This value is set via CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID constant in wp-config.php.', 'capacity-tshirts-stores');
                                } else {
                                    echo esc_html__('Enter your Webflow OAuth Client ID. This can be used to connect multiple Webflow accounts.', 'capacity-tshirts-stores');
                                }
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="webflow_client_secret"><?php echo esc_html__('Webflow Client Secret', 'capacity-tshirts-stores'); ?></label>
                        </th>
                        <td>
                            <input 
                                type="password" 
                                id="webflow_client_secret" 
                                name="webflow_client_secret" 
                                value="<?php echo esc_attr($webflow_client_secret); ?>" 
                                class="regular-text" 
                                <?php echo $has_constants ? 'readonly' : ''; ?>
                            />
                            <p class="description">
                                <?php 
                                if ($has_constants) {
                                    echo esc_html__('This value is set via CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET constant in wp-config.php.', 'capacity-tshirts-stores');
                                } else {
                                    echo esc_html__('Enter your Webflow OAuth Client Secret. Keep this secure.', 'capacity-tshirts-stores');
                                }
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php if (!$has_constants) : ?>
                    <?php submit_button(); ?>
                <?php endif; ?>
                <input type="hidden" name="capacity_tshirts_stores_settings" value="1" />
            </form>
        </div>
        <?php
    }

    /**
     * Render activity log page (displays Log Reports).
     *
     * @return void
     */
    public function render_activity_log_page(): void {
        $page = new Log_History_Page($this->log_repository);
        $page->render();
    }

    /**
     * Render OAuth callback page.
     *
     * @return void
     */
    public function render_oauth_callback_page(): void {
        $page = new Pages\OAuth_Callback_Page($this->store_repository);
        $page->render();
    }
}


<?php
/**
 * Store edit page.
 *
 * @package CapacityTShirtsStores
 * @subpackage Admin\Pages
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Admin\Pages;

use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Database\Repository\Log_Repository;
use CapacityTShirtsStores\Integrations\Webflow_Integration;
use CapacityTShirtsStores\Integrations\Shopify_Integration;
use CapacityTShirtsStores\Core\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Store edit page class.
 */
class Store_Edit_Page {

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
     * Render the edit form.
     *
     * @param array<string, mixed>|null $store Store data.
     * @return void
     */
    public function render(?array $store): void {
        // Handle form submission.
        $this->handle_form_submission();

        $store_id = $store ? absint($store['id']) : 0;
        $title = $store ? $store['title'] : '';
        $store_type = $store ? $store['store_type'] : 'webflow';
        $oauth_data = $store && !empty($store['oauth_data']) ? json_decode($store['oauth_data'], true) : null;
        $store_identifier = $store ? $store['store_identifier'] : null;

        // Get integration instance.
        $integration = $this->get_integration($store_type);
        $stores_list = [];
        $webhooks = null;

        if ($integration && $oauth_data) {
            $stores_list = $integration->get_stores($oauth_data);
            if ($store_identifier) {
                $webhooks = $integration->get_webhooks($store_identifier, $oauth_data);
            }
        }

        // Show success message if OAuth was just completed.
        if (isset($_GET['oauth_success']) && $_GET['oauth_success'] == '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('OAuth authorization successful! Bearer access token has been generated/regenerated. You can now select a store and set up webhooks.', 'capacity-tshirts-stores') . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php echo $store_id > 0 ? esc_html__('Edit Store', 'capacity-tshirts-stores') : esc_html__('Add New Store', 'capacity-tshirts-stores'); ?></h1>

            <form method="post" action="" id="store-edit-form">
                <?php wp_nonce_field('capacity_tshirts_stores_edit_store'); ?>
                <input type="hidden" name="store_id" value="<?php echo esc_attr($store_id); ?>" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="store_title"><?php echo esc_html__('Store Title', 'capacity-tshirts-stores'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="store_title" name="store_title" value="<?php echo esc_attr($title); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <?php if ($store_type !== 'woocommerce') : ?>
                        <tr>
                            <th scope="row">
                                <label for="store_type"><?php echo esc_html__('Store Type', 'capacity-tshirts-stores'); ?></label>
                            </th>
                            <td>
                                <select id="store_type" name="store_type" required>
                                    <option value="webflow" <?php selected($store_type, 'webflow'); ?>><?php echo esc_html__('Webflow', 'capacity-tshirts-stores'); ?></option>
                                    <option value="shopify" <?php selected($store_type, 'shopify'); ?>><?php echo esc_html__('Shopify', 'capacity-tshirts-stores'); ?></option>
                                </select>
                            </td>
                        </tr>
                    <?php else : ?>
                        <input type="hidden" name="store_type" value="woocommerce" />
                    <?php endif; ?>
                </table>

                <?php if ($store_type !== 'woocommerce') : ?>
                    <h2><?php echo esc_html__('OAuth Connection', 'capacity-tshirts-stores'); ?></h2>

                <?php
                // Check if credentials are set for the selected store type.
                $has_credentials = $this->has_oauth_credentials($store_type);
                ?>

                <?php if (empty($oauth_data)) : ?>
                    <?php if ($has_credentials) : ?>
                        <p>
                            <a href="<?php echo esc_url($this->get_oauth_url($store_type, $store_id)); ?>" class="button button-primary" id="oauth-connect-button">
                                <?php echo esc_html__('Connect with OAuth', 'capacity-tshirts-stores'); ?>
                            </a>
                        </p>
                        <p class="description">
                            <?php echo esc_html__('Click the button above to connect your Webflow account. You will be redirected to Webflow\'s OAuth consent screen to authorize the connection. Upon successful authorization, a Bearer access token will be generated to access Webflow API v2.0.', 'capacity-tshirts-stores'); ?>
                        </p>
                    <?php else : ?>
                        <div class="notice notice-error inline">
                            <p><strong><?php echo esc_html__('OAuth Configuration Required', 'capacity-tshirts-stores'); ?></strong></p>
                            <p>
                                <?php 
                                if ($store_type === 'webflow') {
                                    echo esc_html__('Webflow OAuth credentials (Client ID and Client Secret) must be configured. Please contact your administrator or configure them via wp-config.php constants: CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID and CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET', 'capacity-tshirts-stores');
                                } else {
                                    echo esc_html__('OAuth credentials must be configured for this store type.', 'capacity-tshirts-stores');
                                }
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="description">
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php echo esc_html__('OAuth connection established. Bearer access token is active for Webflow API v2.0.', 'capacity-tshirts-stores'); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url($this->get_oauth_url($store_type, $store_id)); ?>" class="button" id="oauth-reconnect-button">
                            <?php echo esc_html__('Reconnect / Regenerate Token', 'capacity-tshirts-stores'); ?>
                        </a>
                        <span class="description" style="display: inline-block; margin-left: 10px;">
                            <?php echo esc_html__('Click to regenerate the Bearer access token.', 'capacity-tshirts-stores'); ?>
                        </span>
                    </p>

                    <?php if (!empty($stores_list)) : ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="store_identifier"><?php echo esc_html__('Select Store', 'capacity-tshirts-stores'); ?></label>
                                </th>
                                <td>
                                    <select id="store_identifier" name="store_identifier" <?php echo $store_id > 0 && $store_identifier ? 'disabled' : ''; ?>>
                                        <option value=""><?php echo esc_html__('-- Select Store --', 'capacity-tshirts-stores'); ?></option>
                                        <?php foreach ($stores_list as $store_item) : ?>
                                            <option value="<?php echo esc_attr($store_item['id']); ?>" <?php selected($store_identifier, $store_item['id']); ?>>
                                                <?php echo esc_html($store_item['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($store_id > 0 && $store_identifier) : ?>
                                        <input type="hidden" name="store_identifier" value="<?php echo esc_attr($store_identifier); ?>" />
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>

                    <?php if ($store_identifier && $webhooks) : ?>
                        <h3><?php echo esc_html__('Webhook Details', 'capacity-tshirts-stores'); ?></h3>
                        <div class="webhook-details">
                            <?php if (is_array($webhooks) && !empty($webhooks)) : ?>
                                <ul>
                                    <?php foreach ($webhooks as $webhook) : ?>
                                        <li>
                                            <strong><?php echo esc_html($webhook['triggerType'] ?? 'N/A'); ?>:</strong>
                                            <?php echo esc_html($webhook['url'] ?? 'N/A'); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p><?php echo esc_html__('No webhooks found.', 'capacity-tshirts-stores'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>

                <?php submit_button($store_id > 0 ? __('Update Store', 'capacity-tshirts-stores') : __('Save Store', 'capacity-tshirts-stores')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle form submission.
     *
     * @return void
     */
    private function handle_form_submission(): void {
        // Only process if form was actually submitted (POST request with data).
        if (empty($_POST) || !isset($_POST['_wpnonce'])) {
            return;
        }

        // Verify nonce for form submissions.
        if (!check_admin_referer('capacity_tshirts_stores_edit_store')) {
            wp_die(__('Security check failed. Please try again.', 'capacity-tshirts-stores'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'capacity-tshirts-stores'));
        }

        // Handle store save.
        if (!isset($_POST['store_title'])) {
            return;
        }

        $store_id = isset($_POST['store_id']) ? absint($_POST['store_id']) : 0;
        $title = sanitize_text_field($_POST['store_title']);
        $store_type = sanitize_text_field($_POST['store_type']);
        $store_identifier = isset($_POST['store_identifier']) ? sanitize_text_field($_POST['store_identifier']) : null;

        if (empty($title) || empty($store_type)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Please fill in all required fields.', 'capacity-tshirts-stores') . '</p></div>';
            return;
        }

        $data = [
            'title' => $title,
            'store_type' => $store_type,
        ];

        if ($store_identifier) {
            $data['store_identifier'] = $store_identifier;
        }

        // Get existing store to preserve OAuth data.
        if ($store_id > 0) {
            $existing_store = $this->store_repository->get_by_id($store_id);
            if ($existing_store) {
                if (!empty($existing_store['oauth_data'])) {
                    $oauth_data = json_decode($existing_store['oauth_data'], true);
                } else {
                    $oauth_data = null;
                }

                // If store identifier is set and OAuth is connected, create webhooks.
                // Only create webhooks if webhook_status is not already 'active' or if explicitly requested
                if ($store_identifier && $oauth_data) {
                    $integration = $this->get_integration($store_type);
                    if ($integration) {
                        // Check if webhooks already exist
                        $existing_webhooks = $integration->get_webhooks($store_identifier, $oauth_data);
                        $has_webhooks = false;
                        $required_trigger_types = ['ecomm_new_order', 'ecomm_order_changed', 'ecomm_inventory_changed'];
                        $existing_trigger_types = [];
                        
                        if (is_array($existing_webhooks) && !empty($existing_webhooks)) {
                            $webhook_url = rest_url('capacity-tshirts-stores/v1/webhook');
                            foreach ($existing_webhooks as $webhook) {
                                $webhook_url_check = $webhook['url'] ?? '';
                                $trigger_type = $webhook['triggerType'] ?? $webhook['trigger_type'] ?? null;
                                
                                // Check if webhook exists with same URL and required trigger type
                                if ($webhook_url_check === $webhook_url && $trigger_type && in_array($trigger_type, $required_trigger_types, true)) {
                                    $existing_trigger_types[] = $trigger_type;
                                }
                            }
                            
                            // Consider webhooks exist if we have all 3 required trigger types
                            $has_webhooks = count($existing_trigger_types) >= 3;
                        }
                        
                        // Only create webhooks if they don't exist or if webhook_status is not 'active'
                        if (!$has_webhooks || $existing_store['webhook_status'] !== 'active') {
                            $webhook_result = $integration->create_webhooks($store_identifier, $oauth_data);
                            if ($webhook_result) {
                                $data['webhook_status'] = 'active';
                                // Detailed logging is already done in create_webhooks() method
                            } else {
                                $data['webhook_status'] = $has_webhooks ? 'active' : 'failed';
                                // Detailed logging is already done in create_webhooks() method
                            }
                        } else {
                            // Webhooks already exist, just update status
                            $data['webhook_status'] = 'active';
                            Logger::log('Webhooks already exist, skipping creation', 'info', [
                                'store_id' => $store_id,
                                'store_identifier' => $store_identifier,
                                'event_type' => 'webhook_creation',
                            ]);
                        }
                    }
                }
            }

            if ($this->store_repository->update($store_id, $data)) {
                Logger::log('Store updated', 'success', ['store_id' => $store_id]);
                wp_redirect(admin_url('admin.php?page=capacity-tshirts-stores&updated=1'));
                exit;
            }
        } else {
            $new_store_id = $this->store_repository->create($data);
            if ($new_store_id) {
                Logger::log('Store created', 'success', ['store_id' => $new_store_id]);

                // If store identifier is set, create webhooks.
                if ($store_identifier) {
                    // OAuth data should be set via callback, so we'll handle webhooks on next edit.
                }

                wp_redirect(admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . $new_store_id . '&created=1'));
                exit;
            }
        }

        echo '<div class="notice notice-error"><p>' . esc_html__('Error saving store.', 'capacity-tshirts-stores') . '</p></div>';
    }

    /**
     * Get OAuth URL.
     *
     * @param string $store_type Store type.
     * @param int $store_id Store ID.
     * @return string
     */
    private function get_oauth_url(string $store_type, int $store_id = 0): string {
        $integration = $this->get_integration($store_type);
        if (!$integration) {
            return '#';
        }

        return $integration->get_oauth_url(['store_id' => $store_id]);
    }

    /**
     * Get integration instance.
     *
     * @param string $store_type Store type.
     * @return \CapacityTShirtsStores\Integrations\Store_Interface|null
     */
    private function get_integration(string $store_type): ?\CapacityTShirtsStores\Integrations\Store_Interface {
        return match ($store_type) {
            'webflow' => new Webflow_Integration(),
            'shopify' => new Shopify_Integration(),
            default => null,
        };
    }

    /**
     * Check if OAuth credentials exist for a store type.
     * Checks constants first (wp-config.php), then falls back to options.
     *
     * @param string $store_type Store type.
     * @return bool
     */
    private function has_oauth_credentials(string $store_type): bool {
        if ($store_type === 'webflow') {
            // Check for constants first (configured in wp-config.php).
            $client_id = defined('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID') ? CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID : '';
            $client_secret = defined('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET') ? CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET : '';

            // If not in constants, check options (for backward compatibility).
            if (empty($client_id)) {
                $client_id = get_option('capacity_tshirts_webflow_client_id', '');
            }
            if (empty($client_secret)) {
                $client_secret = get_option('capacity_tshirts_webflow_client_secret', '');
            }

            return !empty($client_id) && !empty($client_secret);
        }

        // Add other store types as needed.
        return false;
    }
}


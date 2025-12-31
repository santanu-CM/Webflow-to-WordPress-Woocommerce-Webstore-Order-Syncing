<?php
/**
 * OAuth callback page.
 *
 * @package CapacityTShirtsStores
 * @subpackage Admin\Pages
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Admin\Pages;

use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Integrations\Webflow_Integration;
use CapacityTShirtsStores\Integrations\Shopify_Integration;
use CapacityTShirtsStores\Core\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OAuth callback page class.
 */
class OAuth_Callback_Page {

    /**
     * Store repository instance.
     *
     * @var Store_Repository
     */
    private Store_Repository $store_repository;

    /**
     * Constructor.
     *
     * @param Store_Repository $store_repository Store repository.
     */
    public function __construct(Store_Repository $store_repository) {
        $this->store_repository = $store_repository;
    }

    /**
     * Render the callback page.
     *
     * @return void
     */
    public function render(): void {
        // Get OAuth parameters.
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

        // Extract store ID from state if present.
        $store_id = 0;
        if (strpos($state, '|') !== false) {
            $parts = explode('|', $state);
            $state = $parts[0];
            $store_id = isset($parts[1]) ? absint($parts[1]) : 0;
        }

        // Verify nonce.
        if (!wp_verify_nonce($state, 'webflow_oauth_' . get_current_user_id())) {
            wp_die(__('Security check failed.', 'capacity-tshirts-stores'));
        }

        if (!empty($error)) {
            Logger::log_oauth('webflow', false, ['error' => $error], $store_id);
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('OAuth authorization failed.', 'capacity-tshirts-stores') . '</p></div></div>';
            return;
        }

        if (empty($code)) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('OAuth code not provided.', 'capacity-tshirts-stores') . '</p></div></div>';
            return;
        }

        // Determine store type (default to webflow for now).
        $store_type = 'webflow';
        if ($store_id > 0) {
            $store = $this->store_repository->get_by_id($store_id);
            if ($store) {
                $store_type = $store['store_type'];
            }
        }

        // Get integration and handle callback.
        $integration = $this->get_integration($store_type);
        if (!$integration) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Invalid store type.', 'capacity-tshirts-stores') . '</p></div></div>';
            return;
        }

        $oauth_data = $integration->handle_oauth_callback(['code' => $code]);

        if (!$oauth_data) {
            Logger::log_oauth($store_type, false, [], $store_id);
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Failed to obtain OAuth Bearer access token. Please try again.', 'capacity-tshirts-stores') . '</p></div></div>';
            return;
        }

        // Update or create store with Bearer access token.
        if ($store_id > 0) {
            $this->store_repository->update($store_id, [
                'oauth_data' => $oauth_data,
            ]);
            Logger::log_oauth($store_type, true, ['action' => 'token_regenerated'], $store_id);
            $redirect_url = admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . $store_id . '&oauth_success=1');
        } else {
            // Create new store with OAuth data (will need to be completed with title and store selection).
            $new_store_id = $this->store_repository->create([
                'title' => 'New ' . ucfirst($store_type) . ' Store',
                'store_type' => $store_type,
                'oauth_data' => $oauth_data,
            ]);
            Logger::log_oauth($store_type, true, ['action' => 'token_generated'], $new_store_id);
            $redirect_url = admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . $new_store_id . '&oauth_success=1');
        }

        wp_redirect($redirect_url);
        exit;
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
}


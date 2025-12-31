<?php
/**
 * AJAX handler for order updates.
 *
 * @package CapacityTShirtsStores
 * @subpackage Admin
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Admin;

use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Database\Repository\Order_Repository;
use CapacityTShirtsStores\Integrations\Webflow_Integration;
use CapacityTShirtsStores\Core\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler class.
 */
class Ajax_Handler {

    /**
     * Store repository instance.
     *
     * @var Store_Repository
     */
    private Store_Repository $store_repository;

    /**
     * Order repository instance.
     *
     * @var Order_Repository
     */
    private Order_Repository $order_repository;

    /**
     * Constructor.
     *
     * @param Store_Repository $store_repository Store repository.
     * @param Order_Repository $order_repository Order repository.
     */
    public function __construct(Store_Repository $store_repository, Order_Repository $order_repository) {
        $this->store_repository = $store_repository;
        $this->order_repository = $order_repository;
    }

    /**
     * Register AJAX handlers.
     *
     * @return void
     */
    public function register(): void {
        add_action('wp_ajax_capacity_tshirts_update_webflow_shipping', [$this, 'handle_update_shipping']);
        add_action('wp_ajax_capacity_tshirts_update_webflow_comment', [$this, 'handle_update_comment']);
        add_action('wp_ajax_capacity_tshirts_update_webflow_status', [$this, 'handle_update_status']);
    }

    /**
     * Handle shipping information update.
     *
     * @return void
     */
    public function handle_update_shipping(): void {
        // Check permissions.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'capacity-tshirts-stores')]);
        }

        // Verify nonce.
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'capacity_tshirts_update_webflow_shipping_' . $order_id)) {
            wp_send_json_error(['message' => __('Security check failed.', 'capacity-tshirts-stores')]);
        }

        // Get order.
        $order = $this->order_repository->get_by_id($order_id);
        if (!$order || $order['platform'] !== 'webflow') {
            wp_send_json_error(['message' => __('Order not found or not a Webflow order.', 'capacity-tshirts-stores')]);
        }

        // Get store.
        $store = $order['store_id'] ? $this->store_repository->get_by_id((int) $order['store_id']) : null;
        if (!$store || $store['store_type'] !== 'webflow' || empty($store['store_identifier'])) {
            wp_send_json_error(['message' => __('Store not found or invalid.', 'capacity-tshirts-stores')]);
        }

        // Get OAuth data.
        $oauth_data = !empty($store['oauth_data']) ? json_decode($store['oauth_data'], true) : [];
        if (empty($oauth_data['access_token'])) {
            wp_send_json_error(['message' => __('Store not authenticated with Webflow.', 'capacity-tshirts-stores')]);
        }

        // Prepare update data.
        $update_data = [];
        if (isset($_POST['shipping_provider'])) {
            $update_data['shippingProvider'] = sanitize_text_field($_POST['shipping_provider']);
        }
        if (isset($_POST['shipping_tracking'])) {
            $update_data['shippingTracking'] = sanitize_text_field($_POST['shipping_tracking']);
        }
        if (isset($_POST['shipping_tracking_url'])) {
            $update_data['shippingTrackingURL'] = esc_url_raw($_POST['shipping_tracking_url']);
        }

        if (empty($update_data)) {
            wp_send_json_error(['message' => __('No data to update.', 'capacity-tshirts-stores')]);
        }

        // Update order via Webflow API.
        $integration = new Webflow_Integration();
        $updated_order = $integration->update_order(
            $store['store_identifier'],
            $order['platform_order_id'],
            $oauth_data,
            $update_data
        );

        if ($updated_order === false) {
            wp_send_json_error(['message' => __('Failed to update order on Webflow. Please check logs for details.', 'capacity-tshirts-stores')]);
        }

        // Check if error is about missing OAuth scopes.
        if (is_array($updated_order) && isset($updated_order['error']) && $updated_order['error'] === 'MISSING_SCOPES') {
            $store_edit_url = admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . absint($store['id']));
            $error_message = sprintf(
                __('Your Webflow store connection is missing the required permissions (ecommerce:write). Please <a href="%s">reconnect your store</a> to grant the necessary permissions.', 'capacity-tshirts-stores'),
                esc_url($store_edit_url)
            );
            wp_send_json_error(['message' => $error_message]);
        }

        // Update local order data.
        $order_data = !empty($order['order_data']) ? json_decode($order['order_data'], true) : [];
        if (!isset($order_data['payload'])) {
            $order_data['payload'] = [];
        }

        // Merge updated data into payload.
        foreach ($update_data as $key => $value) {
            // Map camelCase to the format used in payload.
            if ($key === 'shippingProvider') {
                $order_data['payload']['shippingProvider'] = $value;
            } elseif ($key === 'shippingTracking') {
                $order_data['payload']['shippingTracking'] = $value;
            } elseif ($key === 'shippingTrackingURL') {
                $order_data['payload']['shippingTrackingURL'] = $value;
            }
        }

        // Update order in database.
        $this->order_repository->update($order_id, ['order_data' => $order_data]);

        wp_send_json_success([
            'message' => __('Shipping information updated successfully.', 'capacity-tshirts-stores'),
            'order' => $updated_order,
        ]);
    }

    /**
     * Handle comment update.
     *
     * @return void
     */
    public function handle_update_comment(): void {
        // Check permissions.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'capacity-tshirts-stores')]);
        }

        // Verify nonce.
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'capacity_tshirts_update_webflow_comment_' . $order_id)) {
            wp_send_json_error(['message' => __('Security check failed.', 'capacity-tshirts-stores')]);
        }

        // Get order.
        $order = $this->order_repository->get_by_id($order_id);
        if (!$order || $order['platform'] !== 'webflow') {
            wp_send_json_error(['message' => __('Order not found or not a Webflow order.', 'capacity-tshirts-stores')]);
        }

        // Get store.
        $store = $order['store_id'] ? $this->store_repository->get_by_id((int) $order['store_id']) : null;
        if (!$store || $store['store_type'] !== 'webflow' || empty($store['store_identifier'])) {
            wp_send_json_error(['message' => __('Store not found or invalid.', 'capacity-tshirts-stores')]);
        }

        // Get OAuth data.
        $oauth_data = !empty($store['oauth_data']) ? json_decode($store['oauth_data'], true) : [];
        if (empty($oauth_data['access_token'])) {
            wp_send_json_error(['message' => __('Store not authenticated with Webflow.', 'capacity-tshirts-stores')]);
        }

        // Prepare update data.
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        $update_data = ['comment' => $comment];

        // Update order via Webflow API.
        $integration = new Webflow_Integration();
        $updated_order = $integration->update_order(
            $store['store_identifier'],
            $order['platform_order_id'],
            $oauth_data,
            $update_data
        );

        if ($updated_order === false) {
            wp_send_json_error(['message' => __('Failed to update order on Webflow. Please check logs for details.', 'capacity-tshirts-stores')]);
        }

        // Check if error is about missing OAuth scopes.
        if (is_array($updated_order) && isset($updated_order['error']) && $updated_order['error'] === 'MISSING_SCOPES') {
            $store_edit_url = admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . absint($store['id']));
            $error_message = sprintf(
                __('Your Webflow store connection is missing the required permissions (ecommerce:write). Please <a href="%s">reconnect your store</a> to grant the necessary permissions.', 'capacity-tshirts-stores'),
                esc_url($store_edit_url)
            );
            wp_send_json_error(['message' => $error_message]);
        }

        // Update local order data.
        $order_data = !empty($order['order_data']) ? json_decode($order['order_data'], true) : [];
        if (!isset($order_data['payload'])) {
            $order_data['payload'] = [];
        }
        $order_data['payload']['comment'] = $comment;

        // Update order in database.
        $this->order_repository->update($order_id, ['order_data' => $order_data]);

        wp_send_json_success([
            'message' => __('Comment updated successfully.', 'capacity-tshirts-stores'),
            'order' => $updated_order,
        ]);
    }

    /**
     * Handle order status update.
     *
     * @return void
     */
    public function handle_update_status(): void {
        // Check permissions.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'capacity-tshirts-stores')]);
        }

        // Verify nonce.
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'capacity_tshirts_update_webflow_status_' . $order_id)) {
            wp_send_json_error(['message' => __('Security check failed.', 'capacity-tshirts-stores')]);
        }

        // Get order.
        $order = $this->order_repository->get_by_id($order_id);
        if (!$order || $order['platform'] !== 'webflow') {
            wp_send_json_error(['message' => __('Order not found or not a Webflow order.', 'capacity-tshirts-stores')]);
        }

        // Prevent status updates if order is already refunded.
        $current_status = $order['status'] ?? 'pending';
        if ($current_status === 'refunded') {
            wp_send_json_error(['message' => __('This order has been refunded and cannot be updated further.', 'capacity-tshirts-stores')]);
        }

        // Get store.
        $store = $order['store_id'] ? $this->store_repository->get_by_id((int) $order['store_id']) : null;
        if (!$store || $store['store_type'] !== 'webflow' || empty($store['store_identifier'])) {
            wp_send_json_error(['message' => __('Store not found or invalid.', 'capacity-tshirts-stores')]);
        }

        // Get OAuth data.
        $oauth_data = !empty($store['oauth_data']) ? json_decode($store['oauth_data'], true) : [];
        if (empty($oauth_data['access_token'])) {
            wp_send_json_error(['message' => __('Store not authenticated with Webflow.', 'capacity-tshirts-stores')]);
        }

        // Get status action.
        $status_action = isset($_POST['order_status']) ? sanitize_text_field($_POST['order_status']) : '';
        if (!in_array($status_action, ['fulfill', 'unfulfill', 'refund'], true)) {
            wp_send_json_error(['message' => __('Invalid status action.', 'capacity-tshirts-stores')]);
        }

        $integration = new Webflow_Integration();
        $updated_order = false;
        $new_status = $order['status'];
        $success_message = '';

        // Call appropriate API method based on action.
        switch ($status_action) {
            case 'fulfill':
                $send_email = isset($_POST['send_fulfillment_email']) && $_POST['send_fulfillment_email'] === '1';
                $updated_order = $integration->fulfill_order(
                    $store['store_identifier'],
                    $order['platform_order_id'],
                    $oauth_data,
                    $send_email
                );
                $new_status = 'completed';
                $success_message = __('Order fulfilled successfully.', 'capacity-tshirts-stores');
                break;

            case 'unfulfill':
                $updated_order = $integration->unfulfill_order(
                    $store['store_identifier'],
                    $order['platform_order_id'],
                    $oauth_data
                );
                $new_status = 'pending';
                $success_message = __('Order unfulfilled successfully.', 'capacity-tshirts-stores');
                break;

            case 'refund':
                $refund_reason = isset($_POST['refund_reason']) && !empty($_POST['refund_reason'])
                    ? sanitize_text_field($_POST['refund_reason'])
                    : null;
                $updated_order = $integration->refund_order(
                    $store['store_identifier'],
                    $order['platform_order_id'],
                    $oauth_data,
                    $refund_reason
                );
                $new_status = 'refunded';
                $success_message = __('Order refunded successfully.', 'capacity-tshirts-stores');
                break;
        }

        if ($updated_order === false) {
            wp_send_json_error(['message' => __('Failed to update order status on Webflow. Please check logs for details.', 'capacity-tshirts-stores')]);
        }

        // Check if error is about missing OAuth scopes.
        if (is_array($updated_order) && isset($updated_order['error']) && $updated_order['error'] === 'MISSING_SCOPES') {
            $store_edit_url = admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . absint($store['id']));
            $error_message = sprintf(
                __('Your Webflow store connection is missing the required permissions (ecommerce:write). Please <a href="%s">reconnect your store</a> to grant the necessary permissions.', 'capacity-tshirts-stores'),
                esc_url($store_edit_url)
            );
            wp_send_json_error(['message' => $error_message]);
        }

        // Update local order data and status.
        $order_data = !empty($order['order_data']) ? json_decode($order['order_data'], true) : [];
        if (is_array($updated_order)) {
            $order_data['payload'] = $updated_order;
        }

        // Update order in database.
        $this->order_repository->update($order_id, [
            'status' => $new_status,
            'order_data' => $order_data,
        ]);

        wp_send_json_success([
            'message' => $success_message,
            'order' => $updated_order,
            'new_status' => $new_status,
        ]);
    }
}

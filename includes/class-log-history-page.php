<?php
/**
 * Log history page.
 *
 * @package CapacityTShirtsStores
 * @subpackage Admin\Pages
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Admin\Pages;

use CapacityTShirtsStores\Database\Repository\Log_Repository;
use CapacityTShirtsStores\Database\Repository\Store_Repository;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log history page class.
 */
class Log_History_Page {

    /**
     * Log repository instance.
     *
     * @var Log_Repository
     */
    private Log_Repository $log_repository;

    /**
     * Store repository instance.
     *
     * @var Store_Repository
     */
    private Store_Repository $store_repository;

    /**
     * Constructor.
     *
     * @param Log_Repository $log_repository Log repository.
     */
    public function __construct(Log_Repository $log_repository) {
        $this->log_repository = $log_repository;
        $this->store_repository = new Store_Repository();
    }

    /**
     * Render the page.
     *
     * @return void
     */
    public function render(): void {
        // Get filter parameters.
        $store_id = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? absint($_GET['store_id']) : null;
        $event_type = isset($_GET['event_type']) && $_GET['event_type'] !== '' ? sanitize_text_field($_GET['event_type']) : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? sanitize_text_field($_GET['status']) : null;
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        // Get logs.
        $args = [
            'store_id' => $store_id,
            'event_type' => $event_type,
            'status' => $status,
            'limit' => $per_page,
            'offset' => $offset,
        ];

        $logs = $this->log_repository->get_all($args);
        $total_logs = $this->log_repository->count($args);
        $total_pages = ceil($total_logs / $per_page);

        // Get stores for filter.
        $stores = $this->store_repository->get_all();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Log History', 'capacity-tshirts-stores'); ?></h1>

            <div class="log-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="capacity-tshirts-stores-activity-log" />
                    <select name="store_id">
                        <option value=""><?php echo esc_html__('All Stores', 'capacity-tshirts-stores'); ?></option>
                        <?php foreach ($stores as $store) : ?>
                            <option value="<?php echo esc_attr($store['id']); ?>" <?php selected($store_id, $store['id']); ?>>
                                <?php echo esc_html($store['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="event_type">
                        <option value=""><?php echo esc_html__('All Event Types', 'capacity-tshirts-stores'); ?></option>
                        <option value="general" <?php selected($event_type, 'general'); ?>><?php echo esc_html__('General', 'capacity-tshirts-stores'); ?></option>
                        <option value="oauth_attempt" <?php selected($event_type, 'oauth_attempt'); ?>><?php echo esc_html__('OAuth Attempt', 'capacity-tshirts-stores'); ?></option>
                        <option value="webhook_creation" <?php selected($event_type, 'webhook_creation'); ?>><?php echo esc_html__('Webhook Creation', 'capacity-tshirts-stores'); ?></option>
                        <option value="webhook_callback" <?php selected($event_type, 'webhook_callback'); ?>><?php echo esc_html__('Webhook Callback', 'capacity-tshirts-stores'); ?></option>
                    </select>
                    <select name="status">
                        <option value=""><?php echo esc_html__('All Statuses', 'capacity-tshirts-stores'); ?></option>
                        <option value="info" <?php selected($status, 'info'); ?>><?php echo esc_html__('Info', 'capacity-tshirts-stores'); ?></option>
                        <option value="success" <?php selected($status, 'success'); ?>><?php echo esc_html__('Success', 'capacity-tshirts-stores'); ?></option>
                        <option value="warning" <?php selected($status, 'warning'); ?>><?php echo esc_html__('Warning', 'capacity-tshirts-stores'); ?></option>
                        <option value="error" <?php selected($status, 'error'); ?>><?php echo esc_html__('Error', 'capacity-tshirts-stores'); ?></option>
                    </select>
                    <?php submit_button(__('Filter', 'capacity-tshirts-stores'), 'secondary', '', false); ?>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('ID', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Store', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Event Type', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Status', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Message', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Created At', 'capacity-tshirts-stores'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                        <tr>
                            <td colspan="6"><?php echo esc_html__('No logs found.', 'capacity-tshirts-stores'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($logs as $log) : ?>
                            <?php
                            $payload = !empty($log['payload']) ? json_decode($log['payload'], true) : [];
                            $message = $payload['message'] ?? '';
                            // Check if store_id exists and is valid (not null, not 0)
                            $store = null;
                            if (!empty($log['store_id']) && absint($log['store_id']) > 0) {
                                $store = $this->store_repository->get_by_id((int) $log['store_id']);
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($log['id']); ?></td>
                                <td>
                                    <?php if ($store) : ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . absint($store['id']))); ?>">
                                            <?php echo esc_html($store['title']); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html__('N/A', 'capacity-tshirts-stores'); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log['event_type']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($log['status']); ?>">
                                        <?php echo esc_html(ucfirst($log['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($message); ?>
                                    <?php 
                                    // Display detailed error information from context
                                    $context = $payload['context'] ?? [];
                                    if (!empty($context) && is_array($context)) : 
                                        $error_details = [];
                                        
                                        if (isset($context['response_code'])) {
                                            $error_details[] = sprintf(__('Response Code: %s', 'capacity-tshirts-stores'), $context['response_code']);
                                        }
                                        
                                        if (isset($context['error'])) {
                                            $error_msg = is_array($context['error']) 
                                                ? (isset($context['error']['message']) ? $context['error']['message'] : wp_json_encode($context['error'], JSON_PRETTY_PRINT))
                                                : $context['error'];
                                            $error_details[] = sprintf(__('Error: %s', 'capacity-tshirts-stores'), $error_msg);
                                        }
                                        
                                        if (isset($context['trigger_type'])) {
                                            $error_details[] = sprintf(__('Trigger Type: %s', 'capacity-tshirts-stores'), $context['trigger_type']);
                                        }
                                        
                                        if (!empty($error_details)) : ?>
                                            <br><small style="color: #d63638; display: block; margin-top: 5px;">
                                                <?php echo esc_html(implode(' | ', $error_details)); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages log-pagination">
                        <?php
                        // Build base URL with current filter parameters
                        $base_url = admin_url('admin.php');
                        $query_args = [
                            'page' => 'capacity-tshirts-stores-activity-log',
                        ];
                        if ($store_id !== null) {
                            $query_args['store_id'] = $store_id;
                        }
                        if ($event_type !== null) {
                            $query_args['event_type'] = $event_type;
                        }
                        if ($status !== null) {
                            $query_args['status'] = $status;
                        }
                        
                        // Previous page URL
                        $prev_url = null;
                        if ($paged > 1) {
                            $prev_query_args = array_merge($query_args, ['paged' => $paged - 1]);
                            $prev_url = add_query_arg($prev_query_args, $base_url);
                        }
                        
                        // Next page URL
                        $next_url = null;
                        if ($paged < $total_pages) {
                            $next_query_args = array_merge($query_args, ['paged' => $paged + 1]);
                            $next_url = add_query_arg($next_query_args, $base_url);
                        }
                        ?>
                        <span class="displaying-num">
                            <?php
                            printf(
                                /* translators: 1: current page number, 2: total pages */
                                esc_html__('Page %1$d of %2$d', 'capacity-tshirts-stores'),
                                $paged,
                                $total_pages
                            );
                            ?>
                        </span>
                        <span class="pagination-links">
                            <?php if ($prev_url) : ?>
                                <a class="button pagination-prev" href="<?php echo esc_url($prev_url); ?>" aria-label="<?php echo esc_attr__('Previous page', 'capacity-tshirts-stores'); ?>">
                                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    <?php echo esc_html__('Previous', 'capacity-tshirts-stores'); ?>
                                </a>
                            <?php else : ?>
                                <span class="button pagination-prev disabled" aria-label="<?php echo esc_attr__('Previous page', 'capacity-tshirts-stores'); ?>">
                                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                                    <?php echo esc_html__('Previous', 'capacity-tshirts-stores'); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($next_url) : ?>
                                <a class="button pagination-next" href="<?php echo esc_url($next_url); ?>" aria-label="<?php echo esc_attr__('Next page', 'capacity-tshirts-stores'); ?>">
                                    <?php echo esc_html__('Next', 'capacity-tshirts-stores'); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </a>
                            <?php else : ?>
                                <span class="button pagination-next disabled" aria-label="<?php echo esc_attr__('Next page', 'capacity-tshirts-stores'); ?>">
                                    <?php echo esc_html__('Next', 'capacity-tshirts-stores'); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}


<?php
/**
 * Store list page.
 *
 * @package CapacityTShirtsStores
 * @subpackage Admin\Pages
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Admin\Pages;

use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Database\Repository\Log_Repository;
use CapacityTShirtsStores\Core\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Store list page class.
 */
class Store_List_Page {

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
     * Render the page.
     *
     * @return void
     */
    public function render(): void {
        // Handle actions.
        $this->handle_actions();

        // Get stores.
        $stores = $this->store_repository->get_all();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html__('Store List', 'capacity-tshirts-stores'); ?>
            </h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=capacity-tshirts-stores&action=add')); ?>" class="page-title-action">
                <?php echo esc_html__('Add New Store', 'capacity-tshirts-stores'); ?>
            </a>
            <hr class="wp-header-end">

            <?php if (isset($_GET['action']) && $_GET['action'] === 'add' || (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']))) : ?>
                <?php $this->render_edit_form(); ?>
            <?php else : ?>
                <?php $this->render_store_table($stores); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle actions.
     *
     * @return void
     */
    private function handle_actions(): void {
        // Delete action.
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_store_' . $_GET['id'])) {
                wp_die(__('Security check failed.', 'capacity-tshirts-stores'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to perform this action.', 'capacity-tshirts-stores'));
            }

            $id = absint($_GET['id']);
            if ($this->store_repository->delete($id)) {
                Logger::log('Store deleted', 'info', ['store_id' => $id]);
                wp_redirect(admin_url('admin.php?page=capacity-tshirts-stores&deleted=1'));
                exit;
            }
        }
    }

    /**
     * Render store table.
     *
     * @param array<int, array<string, mixed>> $stores Stores data.
     * @return void
     */
    private function render_store_table(array $stores): void {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Store Title', 'capacity-tshirts-stores'); ?></th>
                    <th scope="col"><?php echo esc_html__('Store Type', 'capacity-tshirts-stores'); ?></th>
                    <th scope="col"><?php echo esc_html__('Connection Status', 'capacity-tshirts-stores'); ?></th>
                    <th scope="col"><?php echo esc_html__('Webhook Setup Status', 'capacity-tshirts-stores'); ?></th>
                    <th scope="col"><?php echo esc_html__('Actions', 'capacity-tshirts-stores'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stores)) : ?>
                    <tr>
                        <td colspan="5"><?php echo esc_html__('No stores found.', 'capacity-tshirts-stores'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($stores as $store) : ?>
                        <?php
                        $is_woocommerce = ($store['store_type'] ?? '') === 'woocommerce';
                        $oauth_data = !empty($store['oauth_data']) ? json_decode($store['oauth_data'], true) : null;
                        $connection_status = !empty($oauth_data) ? 'connected' : 'not_connected';
                        $webhook_status = $store['webhook_status'] ?? 'pending';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($store['title']); ?></strong></td>
                            <td><?php echo esc_html(ucfirst($store['store_type'])); ?></td>
                            <td>
                                <?php if ($is_woocommerce) : ?>
                                    <?php echo esc_html__('N/A', 'capacity-tshirts-stores'); ?>
                                <?php else : ?>
                                    <span class="status-badge status-<?php echo esc_attr($connection_status); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $connection_status))); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_woocommerce) : ?>
                                    <?php echo esc_html__('N/A', 'capacity-tshirts-stores'); ?>
                                <?php else : ?>
                                    <span class="status-badge status-<?php echo esc_attr($webhook_status); ?>">
                                        <?php echo esc_html(ucfirst($webhook_status)); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . absint($store['id']))); ?>" class="button button-small">
                                    <?php echo esc_html__('Edit', 'capacity-tshirts-stores'); ?>
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=capacity-tshirts-stores&action=delete&id=' . absint($store['id'])), 'delete_store_' . absint($store['id']))); ?>" class="button button-small button-link-delete">
                                    <?php echo esc_html__('Delete', 'capacity-tshirts-stores'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render edit form.
     *
     * @return void
     */
    private function render_edit_form(): void {
        $store_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $store = $store_id > 0 ? $this->store_repository->get_by_id($store_id) : null;

        $edit_page = new Store_Edit_Page($this->store_repository, $this->log_repository);
        $edit_page->render($store);
    }
}


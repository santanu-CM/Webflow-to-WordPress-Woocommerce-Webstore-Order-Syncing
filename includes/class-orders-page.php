<?php
/**
 * Orders page.
 *
 * @package CapacityTShirtsStores
 * @subpackage Admin\Pages
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Admin\Pages;

use CapacityTShirtsStores\Database\Repository\Store_Repository;
use CapacityTShirtsStores\Database\Repository\Order_Repository;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orders page class.
 */
class Orders_Page {

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
     */
    public function __construct(Store_Repository $store_repository) {
        $this->store_repository = $store_repository;
        $this->order_repository = new Order_Repository();
    }

    /**
     * Render the page.
     *
     * @return void
     */
    public function render(): void {
        // Check if viewing order details.
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $this->render_order_details(absint($_GET['id']));
            return;
        }

        // Get filter parameters.
        // Check if store_id is set, not empty, and is a valid positive integer
        $store_id_raw = isset($_GET['store_id']) ? $_GET['store_id'] : '';
        $store_id = (!empty($store_id_raw) && is_numeric($store_id_raw) && (int) $store_id_raw > 0) ? (int) $store_id_raw : null;
        $platform = isset($_GET['platform']) && $_GET['platform'] !== '' ? sanitize_text_field($_GET['platform']) : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? sanitize_text_field($_GET['status']) : null;
        $search = isset($_GET['s']) && $_GET['s'] !== '' ? sanitize_text_field($_GET['s']) : null;
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        // Get orders.
        $args = [
            'store_id' => $store_id,
            'platform' => $platform,
            'status' => $status,
            'search' => $search,
            'limit' => $per_page,
            'offset' => $offset,
        ];

        $orders = $this->order_repository->get_all($args);
        $total_orders = $this->order_repository->count($args);
        $total_pages = ceil($total_orders / $per_page);

        // Get stores for filter (cached for 10 minutes since stores don't change often).
        $stores_cache_key = 'cts_stores_list';
        $stores = get_transient($stores_cache_key);
        
        if ($stores === false) {
            $stores = $this->store_repository->get_all();
            set_transient($stores_cache_key, $stores, 10 * MINUTE_IN_SECONDS);
        }

        // Batch fetch stores for orders to avoid N+1 queries.
        $store_ids = array_filter(array_column($orders, 'store_id'));
        $stores_map = [];
        if (!empty($store_ids)) {
            $unique_store_ids = array_unique(array_map('absint', $store_ids));
            foreach ($unique_store_ids as $sid) {
                $store = $this->store_repository->get_by_id($sid);
                if ($store) {
                    $stores_map[$sid] = $store;
                }
            }
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Orders', 'capacity-tshirts-stores'); ?></h1>
            <hr class="wp-header-end">

            <div class="order-filters" style="margin: 20px 0;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="capacity-tshirts-stores-orders" />
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">                        
                        <select name="store_id" style="min-width: 200px;">
                            <option value="" <?php echo ($store_id === null) ? 'selected="selected"' : ''; ?>><?php echo esc_html__('All Stores', 'capacity-tshirts-stores'); ?></option>
                            <?php foreach ($stores as $store) : ?>
                                <option value="<?php echo esc_attr($store['id']); ?>" <?php selected($store_id, (int) $store['id']); ?>>
                                    <?php echo esc_html($store['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="platform" style="min-width: 150px;">
                            <option value=""><?php echo esc_html__('All Platforms', 'capacity-tshirts-stores'); ?></option>
                            <option value="webflow" <?php selected($platform, 'webflow'); ?>><?php echo esc_html__('Webflow', 'capacity-tshirts-stores'); ?></option>
                            <option value="woocommerce" <?php selected($platform, 'woocommerce'); ?>><?php echo esc_html__('WooCommerce', 'capacity-tshirts-stores'); ?></option>
                            <option value="shopify" <?php selected($platform, 'shopify'); ?>><?php echo esc_html__('Shopify', 'capacity-tshirts-stores'); ?></option>
                        </select>
                        <select name="status" style="min-width: 150px;">
                            <option value=""><?php echo esc_html__('All Statuses', 'capacity-tshirts-stores'); ?></option>
                            <option value="pending" <?php selected($status, 'pending'); ?>><?php echo esc_html__('Pending', 'capacity-tshirts-stores'); ?></option>
                            <option value="processing" <?php selected($status, 'processing'); ?>><?php echo esc_html__('Processing', 'capacity-tshirts-stores'); ?></option>
                            <option value="completed" <?php selected($status, 'completed'); ?>><?php echo esc_html__('Completed', 'capacity-tshirts-stores'); ?></option>
                            <option value="on-hold" <?php selected($status, 'on-hold'); ?>><?php echo esc_html__('On Hold', 'capacity-tshirts-stores'); ?></option>
                            <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php echo esc_html__('Cancelled', 'capacity-tshirts-stores'); ?></option>
                            <option value="refunded" <?php selected($status, 'refunded'); ?>><?php echo esc_html__('Refunded', 'capacity-tshirts-stores'); ?></option>
                        </select>
                        <input type="search" name="s" value="<?php echo esc_attr($search ?? ''); ?>" placeholder="<?php echo esc_attr__('Search orders...', 'capacity-tshirts-stores'); ?>" style="min-width: 200px;" />
                        <?php submit_button(__('Filter', 'capacity-tshirts-stores'), 'secondary', '', false); ?>
                        <?php if ($store_id || $platform || $status || $search) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=capacity-tshirts-stores-orders')); ?>" class="button"><?php echo esc_html__('Clear Filters', 'capacity-tshirts-stores'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Order Number', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Platform', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Customer', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Total', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Status', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Date', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Actions', 'capacity-tshirts-stores'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)) : ?>
                        <tr>
                            <td colspan="7"><?php echo esc_html__('No orders found.', 'capacity-tshirts-stores'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($orders as $order) : ?>
                            <?php
                            // Use pre-fetched store from map to avoid N+1 queries.
                            $store = null;
                            if (!empty($order['store_id'])) {
                                $store = $stores_map[(int) $order['store_id']] ?? null;
                            }
                            ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($order['order_number'] ?? $order['platform_order_id']); ?></strong></td>
                                <td>
                                    <span class="platform-badge platform-<?php echo esc_attr($order['platform']); ?>">
                                        <?php echo esc_html(ucfirst($order['platform'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['customer_name']) : ?>
                                        <strong><?php echo esc_html($order['customer_name']); ?></strong><br>
                                        <small><?php echo esc_html($order['customer_email'] ?? ''); ?></small>
                                    <?php else : ?>
                                        <?php echo esc_html__('N/A', 'capacity-tshirts-stores'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $amount = floatval($order['total_amount']);
                                    $currency = $order['currency'] ?? 'USD';
                                    echo esc_html(sprintf('%s %s', $currency, number_format($amount, 2)));
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($order['status']); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('-', ' ', $order['status']))); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $order['order_date'])); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=capacity-tshirts-stores-orders&action=view&id=' . absint($order['id']))); ?>" class="button button-small">
                                        <?php echo esc_html__('View', 'capacity-tshirts-stores'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        // Build base URL with current filter parameters.
                        $base_url = admin_url('admin.php');
                        $query_args = [
                            'page' => 'capacity-tshirts-stores-orders',
                        ];
                        if ($store_id !== null) {
                            $query_args['store_id'] = $store_id;
                        }
                        if ($platform !== null) {
                            $query_args['platform'] = $platform;
                        }
                        if ($status !== null) {
                            $query_args['status'] = $status;
                        }
                        if ($search !== null) {
                            $query_args['s'] = $search;
                        }
                        $base_url = add_query_arg($query_args, $base_url);
                        $base_url = add_query_arg('paged', '%#%', $base_url);

                        echo paginate_links([
                            'base' => $base_url,
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $paged,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render order details page.
     *
     * @param int $order_id Order ID.
     * @return void
     */
    private function render_order_details(int $order_id): void {
        $order = $this->order_repository->get_by_id($order_id);

        if (!$order) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Order not found.', 'capacity-tshirts-stores') . '</p></div></div>';
            return;
        }

        $order_data = !empty($order['order_data']) ? json_decode($order['order_data'], true) : [];
        $store = $order['store_id'] ? $this->store_repository->get_by_id((int) $order['store_id']) : null;

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html__('Order Details', 'capacity-tshirts-stores'); ?>
            </h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=capacity-tshirts-stores-orders')); ?>" class="page-title-action">
                <?php echo esc_html__('Back to Orders', 'capacity-tshirts-stores'); ?>
            </a>
            <hr class="wp-header-end">

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php echo esc_html__('Order Information', 'capacity-tshirts-stores'); ?></span></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('Order Number', 'capacity-tshirts-stores'); ?></th>
                                        <td><strong>#<?php echo esc_html($order['order_number'] ?? $order['platform_order_id']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('Platform', 'capacity-tshirts-stores'); ?></th>
                                        <td>
                                            <span class="platform-badge platform-<?php echo esc_attr($order['platform']); ?>">
                                                <?php echo esc_html(ucfirst($order['platform'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('Status', 'capacity-tshirts-stores'); ?></th>
                                        <td>
                                            <span class="status-badge status-<?php echo esc_attr($order['status']); ?>">
                                                <?php echo esc_html(ucfirst(str_replace('-', ' ', $order['status']))); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('Total Amount', 'capacity-tshirts-stores'); ?></th>
                                        <td>
                                            <strong><?php
                                                $amount = floatval($order['total_amount']);
                                                $currency = $order['currency'] ?? 'USD';
                                                echo esc_html(sprintf('%s %s', $currency, number_format($amount, 2)));
                                            ?></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('Order Date', 'capacity-tshirts-stores'); ?></th>
                                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $order['order_date'])); ?></td>
                                    </tr>
                                    <?php
                                    // Display Payment information if paymentProcessor exists and is not null.
                                    $payment_processor = null;
                                    if (isset($order_data['payload']['paymentProcessor']) && $order_data['payload']['paymentProcessor'] !== null && $order_data['payload']['paymentProcessor'] !== '') {
                                        $payment_processor = $order_data['payload']['paymentProcessor'];
                                    } elseif (!empty($order_data['payment_method'])) {
                                        $payment_processor = $order_data['payment_method'];
                                    }
                                    if ($payment_processor) :
                                        ?>
                                        <tr>
                                            <th scope="row"><?php echo esc_html__('Payment', 'capacity-tshirts-stores'); ?></th>
                                            <td>
                                                <strong><?php echo esc_html(ucfirst(str_replace(['_', '-'], ' ', $payment_processor))); ?></strong>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($store) : ?>
                                        <tr>
                                            <th scope="row"><?php echo esc_html__('Store', 'capacity-tshirts-stores'); ?></th>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=capacity-tshirts-stores&action=edit&id=' . absint($store['id']))); ?>">
                                                    <?php echo esc_html($store['title']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <?php
                        // Display Order Status Update section for Webflow orders.
                        if ($order['platform'] === 'webflow') {
                            $this->render_order_status_update($order);
                        }
                        ?>

                        <?php
                        // Display Order Items section.
                        $this->render_order_items($order_data, $order['platform']);
                        ?>

                        <div class="postbox">
                            <h2 class="hndle"><span><?php echo esc_html__('Customer Information', 'capacity-tshirts-stores'); ?></span></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('Name', 'capacity-tshirts-stores'); ?></th>
                                        <td><?php echo esc_html($order['customer_name'] ?? __('N/A', 'capacity-tshirts-stores')); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php echo esc_html__('Email', 'capacity-tshirts-stores'); ?></th>
                                        <td><?php echo esc_html($order['customer_email'] ?? __('N/A', 'capacity-tshirts-stores')); ?></td>
                                    </tr>
                                </table>

                                <?php
                                // Display Billing and Shipping information.
                                $this->render_addresses($order_data, $order['platform']);
                                ?>
                            </div>
                        </div>

                        <?php
                        // Display Shipping Information and Comment for Webflow orders.
                        if ($order['platform'] === 'webflow') {
                            $this->render_shipping_information($order_data, $order['platform'], $order);
                            $this->render_comment_section($order_data, $order);
                        }
                        ?>

                        <?php if (!empty($order_data)) : ?>
                            <div class="postbox">
                                <h2 class="hndle"><span><?php echo esc_html__('Order Data', 'capacity-tshirts-stores'); ?></span></h2>
                                <div class="inside">
                                    <?php $this->render_order_data_table($order_data); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render order items section.
     *
     * @param array<string, mixed> $order_data Order data.
     * @param string $platform Platform name.
     * @return void
     */
    private function render_order_items(array $order_data, string $platform): void {
        $items = [];

        if ($platform === 'webflow' && !empty($order_data['payload']['purchasedItems'])) {
            foreach ($order_data['payload']['purchasedItems'] as $item) {
                $items[] = [
                    'image' => $item['variantImage']['url'] ?? null,
                    'title' => $item['productName'] ?? '',
                    'variant' => $item['variantName'] ?? '',
                    'sku' => $item['variantSKU'] ?? null,
                    'quantity' => $item['count'] ?? 0,
                    'price' => $item['variantPrice']['value'] ?? 0,
                    'currency' => $item['variantPrice']['unit'] ?? 'USD',
                    'row_total' => $item['rowTotal']['value'] ?? 0,
                ];
            }
        } elseif ($platform === 'woocommerce' && !empty($order_data['items'])) {
            foreach ($order_data['items'] as $item) {
                $product = wc_get_product($item['product_id'] ?? 0);
                $image_url = $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : null;
                
                $items[] = [
                    'image' => $image_url,
                    'title' => $item['name'] ?? '',
                    'variant' => '',
                    'sku' => $item['sku'] ?? null,
                    'quantity' => $item['quantity'] ?? 0,
                    'price' => ($item['total'] ?? 0) / ($item['quantity'] ?? 1),
                    'currency' => $order_data['currency'] ?? 'USD',
                    'row_total' => $item['total'] ?? 0,
                ];
            }
        }

        if (empty($items)) {
            return;
        }
        ?>
        <div class="postbox">
            <h2 class="hndle"><span><?php echo esc_html__('Order Items', 'capacity-tshirts-stores'); ?></span></h2>
            <div class="inside">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 80px;"><?php echo esc_html__('Image', 'capacity-tshirts-stores'); ?></th>
                            <th scope="col"><?php echo esc_html__('Product', 'capacity-tshirts-stores'); ?></th>
                            <th scope="col" style="width: 100px;"><?php echo esc_html__('SKU', 'capacity-tshirts-stores'); ?></th>
                            <th scope="col" style="width: 80px; text-align: center;"><?php echo esc_html__('Quantity', 'capacity-tshirts-stores'); ?></th>
                            <th scope="col" style="width: 120px; text-align: right;"><?php echo esc_html__('Price', 'capacity-tshirts-stores'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td>
                                    <?php if ($item['image']) : ?>
                                        <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['title']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;" />
                                    <?php else : ?>
                                        <div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999;">
                                            <span class="dashicons dashicons-format-image"></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($item['title']); ?></strong>
                                    <?php if (!empty($item['variant'])) : ?>
                                        <br><small style="color: #666;"><?php echo esc_html($item['variant']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['sku']) : ?>
                                        <code><?php echo esc_html($item['sku']); ?></code>
                                    <?php else : ?>
                                        <span style="color: #999;"><?php echo esc_html__('N/A', 'capacity-tshirts-stores'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <strong><?php echo esc_html($item['quantity']); ?></strong>
                                </td>
                                <td style="text-align: right;">
                                    <strong><?php
                                        $currency = $item['currency'] ?? 'USD';
                                        $row_total = floatval($item['row_total']);
                                        echo esc_html(sprintf('%s %s', $currency, number_format($row_total, 2)));
                                    ?></strong>
                                    <br><small style="color: #666;">
                                        <?php
                                        $unit_price = floatval($item['price']);
                                        echo esc_html(sprintf('%s %s each', $currency, number_format($unit_price, 2)));
                                        ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render billing and shipping addresses.
     *
     * @param array<string, mixed> $order_data Order data.
     * @param string $platform Platform name.
     * @return void
     */
    private function render_addresses(array $order_data, string $platform): void {
        $billing = null;
        $shipping = null;

        if ($platform === 'webflow' && !empty($order_data['payload'])) {
            $payload = $order_data['payload'];
            $billing = $payload['billingAddress'] ?? null;
            $shipping = $payload['shippingAddress'] ?? null;
        } elseif ($platform === 'woocommerce') {
            $billing = $order_data['billing'] ?? null;
            $shipping = $order_data['shipping'] ?? null;
        }

        if (!$billing && !$shipping) {
            return;
        }
        ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <!-- Billing Address Column -->
            <div>
                <h3 style="margin-bottom: 10px;"><?php echo esc_html__('Billing Address', 'capacity-tshirts-stores'); ?></h3>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <?php if ($billing) : ?>
                        <?php if ($platform === 'webflow') : ?>
                            <p><strong><?php echo esc_html($billing['addressee'] ?? ''); ?></strong></p>
                            <p><?php echo esc_html($billing['line1'] ?? ''); ?></p>
                            <?php if (!empty($billing['line2'])) : ?>
                                <p><?php echo esc_html($billing['line2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo esc_html(trim(($billing['city'] ?? '') . ', ' . ($billing['state'] ?? '') . ' ' . ($billing['postalCode'] ?? ''))); ?></p>
                            <p><?php echo esc_html($billing['country'] ?? ''); ?></p>
                        <?php else : ?>
                            <p><strong><?php echo esc_html(trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''))); ?></strong></p>
                            <?php if (!empty($billing['company'])) : ?>
                                <p><?php echo esc_html($billing['company']); ?></p>
                            <?php endif; ?>
                            <p><?php echo esc_html($billing['address_1'] ?? ''); ?></p>
                            <?php if (!empty($billing['address_2'])) : ?>
                                <p><?php echo esc_html($billing['address_2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo esc_html(trim(($billing['city'] ?? '') . ', ' . ($billing['state'] ?? '') . ' ' . ($billing['postcode'] ?? ''))); ?></p>
                            <p><?php echo esc_html($billing['country'] ?? ''); ?></p>
                            <?php if (!empty($billing['email'])) : ?>
                                <p><strong><?php echo esc_html__('Email:', 'capacity-tshirts-stores'); ?></strong> <?php echo esc_html($billing['email']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($billing['phone'])) : ?>
                                <p><strong><?php echo esc_html__('Phone:', 'capacity-tshirts-stores'); ?></strong> <?php echo esc_html($billing['phone']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else : ?>
                        <p><?php echo esc_html__('N/A', 'capacity-tshirts-stores'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Shipping Address Column -->
            <div>
                <h3 style="margin-bottom: 10px;"><?php echo esc_html__('Shipping Address', 'capacity-tshirts-stores'); ?></h3>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <?php if ($shipping) : ?>
                        <?php if ($platform === 'webflow') : ?>
                            <p><strong><?php echo esc_html($shipping['addressee'] ?? ''); ?></strong></p>
                            <p><?php echo esc_html($shipping['line1'] ?? ''); ?></p>
                            <?php if (!empty($shipping['line2'])) : ?>
                                <p><?php echo esc_html($shipping['line2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo esc_html(trim(($shipping['city'] ?? '') . ', ' . ($shipping['state'] ?? '') . ' ' . ($shipping['postalCode'] ?? ''))); ?></p>
                            <p><?php echo esc_html($shipping['country'] ?? ''); ?></p>
                        <?php else : ?>
                            <p><strong><?php echo esc_html(trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? ''))); ?></strong></p>
                            <?php if (!empty($shipping['company'])) : ?>
                                <p><?php echo esc_html($shipping['company']); ?></p>
                            <?php endif; ?>
                            <p><?php echo esc_html($shipping['address_1'] ?? ''); ?></p>
                            <?php if (!empty($shipping['address_2'])) : ?>
                                <p><?php echo esc_html($shipping['address_2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo esc_html(trim(($shipping['city'] ?? '') . ', ' . ($shipping['state'] ?? '') . ' ' . ($shipping['postcode'] ?? ''))); ?></p>
                            <p><?php echo esc_html($shipping['country'] ?? ''); ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p><?php echo esc_html__('N/A', 'capacity-tshirts-stores'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render shipping information (for Webflow orders with shipping details).
     *
     * @param array<string, mixed> $order_data Order data.
     * @param string $platform Platform name.
     * @param array<string, mixed> $order Order data from database.
     * @return void
     */
    private function render_shipping_information(array $order_data, string $platform, array $order): void {
        // Only show for Webflow orders.
        if ($platform !== 'webflow') {
            return;
        }

        $payload = $order_data['payload'] ?? [];
        $shipping_provider = $payload['shippingProvider'] ?? '';
        $shipping_tracking = $payload['shippingTracking'] ?? '';
        $shipping_tracking_url = $payload['shippingTrackingURL'] ?? '';

        ?>
        <div class="postbox" id="webflow-shipping-information">
            <h2 class="hndle"><span><?php echo esc_html__('Shipping Information', 'capacity-tshirts-stores'); ?></span></h2>
            <div class="inside">
                <form id="webflow-shipping-form" class="webflow-update-form">
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order['id']); ?>" />
                    <input type="hidden" name="action" value="capacity_tshirts_update_webflow_shipping" />
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('capacity_tshirts_update_webflow_shipping_' . $order['id'])); ?>" />
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="shipping_provider"><?php echo esc_html__('Shipping Provider', 'capacity-tshirts-stores'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="shipping_provider" name="shipping_provider" value="<?php echo esc_attr($shipping_provider); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="shipping_tracking"><?php echo esc_html__('Tracking Number', 'capacity-tshirts-stores'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="shipping_tracking" name="shipping_tracking" value="<?php echo esc_attr($shipping_tracking); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="shipping_tracking_url"><?php echo esc_html__('Tracking URL', 'capacity-tshirts-stores'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="shipping_tracking_url" name="shipping_tracking_url" value="<?php echo esc_attr($shipping_tracking_url); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="update-shipping-btn">
                            <?php echo esc_html__('Update Shipping Information', 'capacity-tshirts-stores'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin-left: 10px; visibility: hidden;"></span>
                    </p>
                </form>
                <div id="shipping-update-message" class="webflow-update-message" style="display: none; margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render comment section for Webflow orders.
     *
     * @param array<string, mixed> $order_data Order data.
     * @param array<string, mixed> $order Order data from database.
     * @return void
     */
    private function render_comment_section(array $order_data, array $order): void {
        $payload = $order_data['payload'] ?? [];
        $comment = $payload['comment'] ?? '';

        ?>
        <div class="postbox" id="webflow-comment-section">
            <h2 class="hndle"><span><?php echo esc_html__('Comment', 'capacity-tshirts-stores'); ?></span></h2>
            <div class="inside">
                <form id="webflow-comment-form" class="webflow-update-form">
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order['id']); ?>" />
                    <input type="hidden" name="action" value="capacity_tshirts_update_webflow_comment" />
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('capacity_tshirts_update_webflow_comment_' . $order['id'])); ?>" />
                    <table class="form-table">
                        <tr>
                            <td colspan="2">
                                <textarea id="order_comment" name="comment" rows="5" class="large-text" placeholder="<?php echo esc_attr__('Enter additional information about this order...', 'capacity-tshirts-stores'); ?>"><?php echo esc_textarea($comment); ?></textarea>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="update-comment-btn">
                            <?php echo esc_html__('Update Comment', 'capacity-tshirts-stores'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin-left: 10px; visibility: hidden;"></span>
                    </p>
                </form>
                <div id="comment-update-message" class="webflow-update-message" style="display: none; margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render order status update section for Webflow orders.
     *
     * @param array<string, mixed> $order Order data from database.
     * @return void
     */
    private function render_order_status_update(array $order): void {
        $current_status = $order['status'] ?? 'pending';
        $is_refunded = $current_status === 'refunded';
        ?>
        <div class="postbox" id="webflow-order-status-update">
            <h2 class="hndle"><span><?php echo esc_html__('Update Order Status', 'capacity-tshirts-stores'); ?></span></h2>
            <div class="inside">
                <?php if ($is_refunded) : ?>
                    <div class="notice notice-info inline">
                        <p><?php echo esc_html__('This order has been refunded and cannot be updated further.', 'capacity-tshirts-stores'); ?></p>
                    </div>
                <?php else : ?>
                    <form id="webflow-status-form" class="webflow-update-form">
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order['id']); ?>" />
                        <input type="hidden" name="action" value="capacity_tshirts_update_webflow_status" />
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('capacity_tshirts_update_webflow_status_' . $order['id'])); ?>" />
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="order_status"><?php echo esc_html__('Order Status', 'capacity-tshirts-stores'); ?></label>
                                </th>
                                <td>
                                    <select id="order_status" name="order_status" class="regular-text" required>
                                        <option value=""><?php echo esc_html__('Select Status...', 'capacity-tshirts-stores'); ?></option>
                                        <option value="fulfill" <?php selected($current_status, 'completed'); ?>><?php echo esc_html__('Fulfill Order (Completed)', 'capacity-tshirts-stores'); ?></option>
                                        <option value="unfulfill" <?php selected($current_status, 'pending'); ?>><?php echo esc_html__('Unfulfill Order (Pending)', 'capacity-tshirts-stores'); ?></option>
                                        <option value="refund" <?php selected($current_status, 'refunded'); ?>><?php echo esc_html__('Refund Order (Refunded)', 'capacity-tshirts-stores'); ?></option>
                                    </select>
                                    <p class="description"><?php echo esc_html__('Select the action to perform on this order.', 'capacity-tshirts-stores'); ?></p>
                                </td>
                            </tr>
                            <tr id="fulfill-email-row" style="display: none;">
                                <th scope="row"></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="send_fulfillment_email" name="send_fulfillment_email" value="1" checked />
                                        <?php echo esc_html__('Send Order Fulfillment Email', 'capacity-tshirts-stores'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr id="refund-reason-row" style="display: none;">
                                <th scope="row">
                                    <label for="refund_reason"><?php echo esc_html__('Refund Reason', 'capacity-tshirts-stores'); ?></label>
                                </th>
                                <td>
                                    <select id="refund_reason" name="refund_reason" class="regular-text">
                                        <option value=""><?php echo esc_html__('None (Optional)', 'capacity-tshirts-stores'); ?></option>
                                        <option value="duplicate"><?php echo esc_html__('Duplicate', 'capacity-tshirts-stores'); ?></option>
                                        <option value="fraudulent"><?php echo esc_html__('Fraudulent', 'capacity-tshirts-stores'); ?></option>
                                        <option value="requested"><?php echo esc_html__('Requested', 'capacity-tshirts-stores'); ?></option>
                                    </select>
                                    <p class="description"><?php echo esc_html__('Select a reason for the refund (optional).', 'capacity-tshirts-stores'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary" id="update-status-btn">
                                <?php echo esc_html__('Update Order Status', 'capacity-tshirts-stores'); ?>
                            </button>
                            <span class="spinner" style="float: none; margin-left: 10px; visibility: hidden;"></span>
                        </p>
                    </form>
                    <div id="status-update-message" class="webflow-update-message" style="display: none; margin-top: 10px;"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render order data as Excel-like table format.
     *
     * @param array<string, mixed> $order_data Order data.
     * @return void
     */
    private function render_order_data_table(array $order_data): void {
        // Flatten nested arrays for table display.
        $flattened_data = $this->flatten_array($order_data);
        ?>
        <div style="overflow-x: auto;">
            <table class="wp-list-table widefat fixed striped" style="min-width: 100%;">
                <thead>
                    <tr>
                        <th scope="col" style="width: 30%;"><?php echo esc_html__('Field', 'capacity-tshirts-stores'); ?></th>
                        <th scope="col"><?php echo esc_html__('Value', 'capacity-tshirts-stores'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flattened_data as $key => $value) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($this->format_field_name($key)); ?></strong></td>
                            <td>
                                <?php
                                if (is_array($value)) {
                                    echo esc_html(wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                                } elseif (is_bool($value)) {
                                    echo $value ? esc_html__('Yes', 'capacity-tshirts-stores') : esc_html__('No', 'capacity-tshirts-stores');
                                } elseif ($value === null) {
                                    echo '<span style="color: #999;">' . esc_html__('N/A', 'capacity-tshirts-stores') . '</span>';
                                } else {
                                    echo esc_html((string) $value);
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Flatten nested array for table display.
     *
     * @param array<string, mixed> $array Array to flatten.
     * @param string $prefix Prefix for keys.
     * @return array<string, mixed> Flattened array.
     */
    private function flatten_array(array $array, string $prefix = ''): array {
        $result = [];
        
        foreach ($array as $key => $value) {
            $new_key = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value) && !empty($value) && !$this->is_associative_array($value)) {
                // For indexed arrays, show as JSON.
                $result[$new_key] = wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } elseif (is_array($value) && !empty($value)) {
                // For associative arrays, recursively flatten.
                $result = array_merge($result, $this->flatten_array($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Check if array is associative.
     *
     * @param array<string, mixed> $array Array to check.
     * @return bool True if associative, false if indexed.
     */
    private function is_associative_array(array $array): bool {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Format field name for display.
     *
     * @param string $key Field key.
     * @return string Formatted field name.
     */
    private function format_field_name(string $key): string {
        // Replace dots with spaces and convert camelCase to Title Case.
        $formatted = str_replace('.', ' → ', $key);
        $formatted = preg_replace('/([a-z])([A-Z])/', '$1 $2', $formatted);
        $formatted = ucwords(str_replace('_', ' ', $formatted));
        return $formatted;
    }
}

# Capacity T-Shirts Stores

A WordPress plugin for managing multiple e-commerce store integrations with OAuth authentication, webhook support, and centralized order management.

## Overview

Capacity T-Shirts Stores is a comprehensive WordPress plugin that enables you to connect and manage multiple e-commerce platforms (Webflow, Shopify, WooCommerce) from a single WordPress dashboard. The plugin provides OAuth-based authentication, real-time webhook integration, and powerful order management capabilities.

## Features

### Multi-Store Management
- Connect multiple stores from different platforms
- Centralized dashboard for all store operations
- Store-specific configuration and settings
- OAuth-based secure authentication

### Order Management
- **Unified Order View**: View all orders from all connected stores in one place
- **Advanced Filtering**: Filter orders by store, platform, status, and search terms
- **Order Details**: Comprehensive order information including:
  - Customer details
  - Order items with images
  - Billing and shipping addresses
  - Payment information
  - Full order data

### Platform Integrations

#### Webflow Integration
- **OAuth 2.0 Authentication** with scoped permissions
- **Real-time Webhooks** for order updates
- **Order Status Management**:
  - Fulfill orders (with optional email notification)
  - Unfulfill orders
  - Refund orders (with reason tracking)
- **Shipping Information Management**:
  - Update shipping provider
  - Update tracking number
  - Update tracking URL
- **Order Comments**: Add and update order comments
- **API v2.0.0** support

#### Shopify Integration
- OAuth-based connection
- Webhook support for order events
- Store management capabilities

#### WooCommerce Integration
- Native WordPress/WooCommerce integration
- Automatic order synchronization
- Real-time order status updates

### Webhook Support
- Automatic webhook creation for connected stores
- Real-time order synchronization
- Event logging and tracking
- Webhook status monitoring

### Activity Logging
- Comprehensive activity log system
- Event tracking and filtering
- Status-based log categorization
- Store-specific log views

### Settings & Configuration
- OAuth client credentials management
- Store-specific settings
- Webhook configuration
- Customizable options

## Requirements

- **WordPress**: 6.8 or higher
- **PHP**: 8.3 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.3+)

### Optional Dependencies
- **WooCommerce**: For WooCommerce store integration

## Installation

1. Upload the `capacity-tshirts-stores` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Stores > Settings** to configure OAuth credentials
4. Go to **Stores > Store List** to add your first store

## Configuration

### Webflow OAuth Setup

1. Create a Webflow OAuth application at [Webflow Developer Portal](https://developers.webflow.com/)
2. Configure OAuth redirect URI: `your-site.com/wp-admin/admin.php?page=capacity-tshirts-stores-oauth-callback`
3. Add your Client ID and Client Secret in **Stores > Settings**
   - Alternatively, define them as constants in `wp-config.php`:
     ```php
     define('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID', 'your-client-id');
     define('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET', 'your-client-secret');
     ```

### Required OAuth Scopes

The plugin requires the following Webflow OAuth scopes:
- `sites:read` - Read site information
- `sites:write` - Manage sites
- `ecommerce:read` - Read e-commerce data
- `ecommerce:write` - Update e-commerce orders

## Directory Structure

```
capacity-tshirts-stores/
├── assets/
│   ├── css/
│   │   └── admin.css          # Admin interface styles
│   └── js/
│       └── admin.js           # Admin JavaScript functionality
├── includes/
│   ├── Core/
│   │   ├── class-activator.php        # Plugin activation handler
│   │   ├── class-deactivator.php      # Plugin deactivation handler
│   │   ├── class-logger.php           # Centralized logging system
│   │   └── class-plugin.php           # Main plugin class
│   ├── Admin/
│   │   ├── class-admin-menu.php       # Admin menu registration
│   │   ├── class-ajax-handler.php     # AJAX request handlers
│   │   ├── class-assets.php           # Asset enqueuing
│   │   ├── class-log-history-page.php # Activity log page
│   │   ├── class-orders-page.php      # Orders listing and details
│   │   ├── class-oauth-callback-page.php # OAuth callback handler
│   │   ├── class-store-edit-page.php  # Store edit/create page
│   │   └── class-store-list-page.php  # Store listing page
│   ├── Database/
│   │   ├── class-schema.php           # Database schema management
│   │   └── Repository/
│   │       ├── class-log-repository.php    # Log data access
│   │       ├── class-order-repository.php # Order data access
│   │       └── class-store-repository.php # Store data access
│   ├── Integrations/
│   │   ├── class-store-interface.php      # Integration interface
│   │   ├── class-webflow-integration.php  # Webflow API integration
│   │   ├── class-shopify-integration.php  # Shopify API integration
│   │   └── class-woocommerce-integration.php # WooCommerce integration
│   ├── Webhooks/
│   │   └── class-webhook-handler.php  # Webhook processing
│   ├── class-autoloader.php           # PSR-4 autoloader
│   └── class-order-normalizer.php     # Order data normalization
└── capacity-tshirts-stores.php       # Main plugin file
```

## Usage

### Adding a Store

1. Navigate to **Stores > Store List**
2. Click **Add New Store**
3. Select store type (Webflow, Shopify, or WooCommerce)
4. Enter store title
5. For Webflow/Shopify:
   - Click **Connect with [Platform]**
   - Complete OAuth authentication
   - Select the store from the list
6. Save the store

### Managing Orders

#### Viewing Orders
- Navigate to **Stores > Orders**
- Use filters to find specific orders:
  - Filter by store
  - Filter by platform (Webflow, WooCommerce, Shopify)
  - Filter by status (Pending, Processing, Completed, etc.)
  - Search by order number, customer name, or email

#### Order Details (Webflow)
For Webflow orders, the order details page provides:

1. **Order Information**: Basic order details, status, totals
2. **Order Items**: Product list with images, quantities, prices
3. **Customer Information**: Name, email, billing and shipping addresses
4. **Shipping Information** (Editable):
   - Shipping Provider
   - Tracking Number
   - Tracking URL
   - Update button for async updates
5. **Comment Section** (Editable):
   - Text area for additional order notes
   - Update button for async updates
6. **Order Status Update**:
   - Fulfill Order (Completed)
   - Unfulfill Order (Pending)
   - Refund Order (Refunded)
   - Conditional fields based on action selected
7. **Order Data**: Complete raw order data in table format

### Webflow Order Management

#### Updating Shipping Information
1. Open order details page
2. Scroll to **Shipping Information** section
3. Update shipping provider, tracking number, or tracking URL
4. Click **Update Shipping Information**
5. Changes are synced to Webflow via API

#### Adding/Updating Comments
1. Open order details page
2. Scroll to **Comment** section
3. Enter or modify the comment text
4. Click **Update Comment**
5. Comment is synced to Webflow

#### Changing Order Status
1. Open order details page
2. Scroll to **Update Order Status** section
3. Select desired action:
   - **Fulfill Order**: Marks order as completed
     - Optional: Check "Send Order Fulfillment Email"
   - **Unfulfill Order**: Reverts order to pending
   - **Refund Order**: Marks order as refunded
     - Optional: Select refund reason (duplicate, fraudulent, requested)
4. Click **Update Order Status**
5. Status is updated in both WordPress and Webflow

**Note**: Orders with "refunded" status cannot be updated further.

### Activity Logs

- Navigate to **Stores > Activity Log**
- View all plugin activities and events
- Filter by store, event type, or status
- Monitor webhook events and API calls

## Database Schema

The plugin creates three main database tables:

### `wp_capacity_tshirts_stores`
Stores store connection information:
- `id` - Primary key
- `title` - Store name
- `store_type` - Platform type (webflow, shopify, woocommerce)
- `store_identifier` - Platform-specific store ID
- `oauth_data` - Encrypted OAuth tokens (JSON)
- `webhook_status` - Webhook connection status
- `created_at`, `updated_at` - Timestamps

### `wp_capacity_tshirts_orders`
Stores order data from all platforms:
- `id` - Primary key
- `store_id` - Foreign key to stores table
- `platform` - Order platform
- `platform_order_id` - Platform-specific order ID (unique per platform)
- `order_number` - Human-readable order number
- `status` - Order status
- `customer_name`, `customer_email` - Customer information
- `total_amount`, `currency` - Order totals
- `order_date` - Order date
- `order_data` - Complete order data (JSON)
- `created_at`, `updated_at` - Timestamps

### `wp_capacity_tshirts_logs`
Activity and event logging:
- `id` - Primary key
- `store_id` - Associated store (optional)
- `event_type` - Event category
- `payload` - Event data (JSON)
- `status` - Log level (info, success, warning, error)
- `created_at` - Timestamp

## API Integration Details

### Webflow API v2.0.0

The plugin uses Webflow's API v2.0.0 for all operations:

#### Endpoints Used:
- `GET /v2/sites` - List available sites
- `POST /v2/sites/:site_id/webhooks` - Create webhooks
- `GET /v2/sites/:site_id/webhooks` - List webhooks
- `PATCH /v2/sites/:site_id/orders/:order_id` - Update order
- `POST /v2/sites/:site_id/orders/:order_id/fulfill` - Fulfill order
- `POST /v2/sites/:site_id/orders/:order_id/unfulfill` - Unfulfill order
- `POST /v2/sites/:site_id/orders/:order_id/refund` - Refund order

#### Webhook Events:
- `ecomm_new_order` - New order created
- `ecomm_order_changed` - Order updated
- `ecomm_inventory_changed` - Inventory updated

## Development

### Code Structure

The plugin follows WordPress coding standards and uses:
- **PSR-4 Autoloading**: Namespace-based class loading
- **Object-Oriented Design**: Modular, extensible architecture
- **Repository Pattern**: Data access layer separation
- **Interface-Based Integration**: Easy to add new platforms

### Adding a New Platform Integration

1. Create a new class implementing `Store_Interface`
2. Implement required methods:
   - `get_type()` - Return platform identifier
   - `get_oauth_url()` - OAuth authorization URL
   - `handle_oauth_callback()` - Process OAuth callback
   - `get_stores()` - Fetch available stores
   - `create_webhooks()` - Set up webhooks
   - `get_webhooks()` - List webhooks
3. Register the integration in the plugin

### Hooks and Filters

The plugin provides various WordPress hooks for extensibility:
- Action hooks for order processing
- Filter hooks for data normalization
- Custom AJAX endpoints for async operations

## Security

- **Nonce Verification**: All forms and AJAX requests use WordPress nonces
- **Capability Checks**: Admin-only functionality requires `manage_options` capability
- **Data Sanitization**: All user input is sanitized and validated
- **OAuth Token Storage**: Tokens are stored securely in the database
- **SQL Injection Prevention**: All database queries use prepared statements

## Troubleshooting

### Webflow OAuth Issues
- Verify OAuth credentials are correct
- Check redirect URI matches exactly
- Ensure required scopes are granted
- Check Activity Log for detailed error messages

### Webhook Not Working
- Verify webhook URL is accessible
- Check webhook status in store edit page
- Review Activity Log for webhook events
- Ensure store has valid OAuth tokens

### Order Updates Failing
- Verify store has `ecommerce:write` scope
- Check OAuth token is not expired
- Review Activity Log for API errors
- Ensure order exists in Webflow

## Support

For issues, questions, or contributions, please contact the development team or refer to the plugin documentation.

## License

GPL v2 or later

## Version

Current Version: 1.0.0

## Changelog

### 1.0.0
- Initial release
- Webflow, Shopify, and WooCommerce integrations
- OAuth authentication
- Webhook support
- Order management system
- Activity logging
- Webflow order status updates
- Shipping information management
- Order comments management

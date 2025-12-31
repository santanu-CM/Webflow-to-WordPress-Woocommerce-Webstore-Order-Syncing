<?php
/**
 * Webflow integration.
 *
 * @package CapacityTShirtsStores
 * @subpackage Integrations
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Integrations;

use CapacityTShirtsStores\Core\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webflow integration class.
 * 
 * Uses Webflow API v2.0.0
 * Documentation: https://developers.webflow.com/data/v2.0.0/reference/rest-introduction
 */
class Webflow_Integration implements Store_Interface {

    /**
     * Webflow API base URL.
     *
     * @var string
     */
    private const API_BASE_URL = 'https://api.webflow.com';

    /**
     * Webflow API version.
     *
     * @var string
     */
    private const API_VERSION = 'v2.0.0';

    /**
     * OAuth authorization URL.
     *
     * @var string
     */
    private const OAUTH_AUTH_URL = 'https://webflow.com/oauth/authorize';

    /**
     * OAuth token URL.
     * API v2.0.0: https://api.webflow.com/oauth/access_token
     *
     * @var string
     */
    private const OAUTH_TOKEN_URL = 'https://api.webflow.com/oauth/access_token';

    /**
     * Get store type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'webflow';
    }

    /**
     * Get OAuth authorization URL.
     *
     * @param array<string, mixed> $args Additional arguments.
     * @return string
     */
    public function get_oauth_url(array $args = []): string {
        $client_id = $this->get_client_id();
        $redirect_uri = $this->get_redirect_uri();
        $state = wp_create_nonce('webflow_oauth_' . get_current_user_id());

        // API v2.0.0 uses scoped permissions
        // Request permissions needed for webhooks and store management
        // ecommerce:read is required for e-commerce webhooks (ecomm_new_order, ecomm_order_changed, ecomm_inventory_changed)
        // ecommerce:write is required for updating orders (shipping info, comments, etc.)
        $params = [
            'client_id' => $client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'scope' => 'sites:read sites:write ecommerce:read ecommerce:write', // v2.0.0 scoped permissions for sites and e-commerce webhooks
            'state' => $state,
        ];

        if (!empty($args['store_id'])) {
            $params['state'] = $state . '|' . absint($args['store_id']);
        }

        return self::OAUTH_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Handle OAuth callback.
     *
     * @param array<string, mixed> $data OAuth callback data.
     * @return array<string, mixed>|false Access token data on success, false on failure.
     */
    public function handle_oauth_callback(array $data): array|false {
        if (empty($data['code'])) {
            Logger::log('Webflow OAuth callback missing code', 'error', ['data' => $data]);
            return false;
        }

        $code = sanitize_text_field($data['code']);
        $client_id = $this->get_client_id();
        $client_secret = $this->get_client_secret();
        $redirect_uri = $this->get_redirect_uri();

        $response = wp_remote_post(
            self::OAUTH_TOKEN_URL,
            [
                'body' => [
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirect_uri, // Required: must match the redirect_uri used in authorization request
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            Logger::log('Webflow OAuth token request failed', 'error', ['error' => $response->get_error_message()]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            Logger::log('Webflow OAuth token request failed', 'error', [
                'response_code' => $response_code,
                'error' => $error_data,
            ]);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $token_data = json_decode($body, true);

        // Debug logging for token response.
        Logger::log('Webflow OAuth token response received', 'info', [
            'response_keys' => array_keys($token_data ?? []),
            'has_access_token' => !empty($token_data['access_token']),
            'token_length' => isset($token_data['access_token']) ? strlen($token_data['access_token']) : 0,
        ]);

        if (empty($token_data['access_token'])) {
            Logger::log('Webflow OAuth token response invalid', 'error', ['response' => $token_data]);
            return false;
        }

        // Don't sanitize access_token or refresh_token - they are already safe from API response
        // and sanitize_text_field can corrupt long token strings
        $access_token = $token_data['access_token'];
        $refresh_token = isset($token_data['refresh_token']) ? $token_data['refresh_token'] : null;

        Logger::log('Webflow OAuth successful', 'success', [
            'store_type' => 'webflow',
            'api_version' => '2.0.0',
            'token_length' => strlen($access_token),
        ]);

        return [
            'access_token' => $access_token, // No sanitization - token is already safe
            'token_type' => isset($token_data['token_type']) ? sanitize_text_field($token_data['token_type']) : 'Bearer',
            'expires_in' => isset($token_data['expires_in']) ? absint($token_data['expires_in']) : null,
            'refresh_token' => $refresh_token, // No sanitization - token is already safe
        ];
    }

    /**
     * Get stores from the connected account.
     *
     * @param array<string, mixed> $oauth_data OAuth data.
     * @return array<int, array<string, mixed>>
     */
    public function get_stores(array $oauth_data): array {
        if (empty($oauth_data['access_token'])) {
            Logger::log('Webflow get stores: missing access token', 'error', ['oauth_data_keys' => array_keys($oauth_data)]);
            return [];
        }

        $access_token = $oauth_data['access_token'];
        
        // Debug: Log token info (first/last few chars only for security)
        $token_preview = strlen($access_token) > 20 
            ? substr($access_token, 0, 10) . '...' . substr($access_token, -10) 
            : '***';
        Logger::log('Webflow get stores: attempting API call', 'info', [
            'token_length' => strlen($access_token),
            'token_preview' => $token_preview,
        ]);

        // API v2.0.0: GET /v2/sites
        $url = self::API_BASE_URL . '/v2/sites';

        $response = wp_remote_get(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json',
                    'X-API-Version' => '2.0.0', // Explicitly specify API version
                ],
            ]
        );

        if (is_wp_error($response)) {
            Logger::log('Webflow get sites request failed', 'error', ['error' => $response->get_error_message()]);
            return [];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            Logger::log('Webflow get sites request failed', 'error', [
                'response_code' => $response_code,
                'error' => $error_data,
            ]);
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // API v2.0.0 response structure: { "sites": [...] }
        if (empty($data['sites']) || !is_array($data['sites'])) {
            return [];
        }

        $stores = [];
        foreach ($data['sites'] as $site) {
            // API v2.0.0 site object structure
            $stores[] = [
                'id' => isset($site['id']) ? sanitize_text_field($site['id']) : '',
                'name' => isset($site['displayName']) ? sanitize_text_field($site['displayName']) : (isset($site['name']) ? sanitize_text_field($site['name']) : ''),
                'short_name' => isset($site['shortName']) ? sanitize_text_field($site['shortName']) : (isset($site['short_name']) ? sanitize_text_field($site['short_name']) : ''),
            ];
        }

        return $stores;
    }

    /**
     * Create webhooks for a store.
     *
     * @param string $store_identifier Store identifier (site ID).
     * @param array<string, mixed> $oauth_data OAuth data.
     * @return array<string, mixed>|false Webhook data on success, false on failure.
     */
    public function create_webhooks(string $store_identifier, array $oauth_data): array|false {
        if (empty($oauth_data['access_token'])) {
            Logger::log('Webflow create webhooks missing access token', 'error', ['oauth_data_keys' => array_keys($oauth_data)]);
            return false;
        }

        $access_token = $oauth_data['access_token'];
        
        // Debug: Log token info (first/last few chars only for security)
        $token_preview = strlen($access_token) > 20 
            ? substr($access_token, 0, 10) . '...' . substr($access_token, -10) 
            : '***';
        Logger::log('Webflow create webhooks: attempting API call', 'info', [
            'site_id' => $store_identifier,
            'token_length' => strlen($access_token),
            'token_preview' => $token_preview,
        ]);

        $webhook_url = $this->get_webhook_callback_url();
        $site_id = sanitize_text_field($store_identifier);

        $trigger_types = [
            'ecomm_new_order',
            'ecomm_order_changed',
            'ecomm_inventory_changed',
        ];

        $created_webhooks = [];
        $errors = [];

        // Get existing webhooks to avoid duplicates
        $existing_webhooks = $this->get_webhooks($store_identifier, $oauth_data);
        $existing_trigger_types = [];
        
        if (is_array($existing_webhooks)) {
            foreach ($existing_webhooks as $existing_webhook) {
                $existing_trigger = $existing_webhook['triggerType'] ?? $existing_webhook['trigger_type'] ?? null;
                $existing_url = $existing_webhook['url'] ?? '';
                
                // Check if webhook exists with same trigger type and URL
                if ($existing_trigger && $existing_url === $webhook_url) {
                    $existing_trigger_types[] = $existing_trigger;
                }
            }
        }

        foreach ($trigger_types as $trigger_type) {
            // Skip if webhook already exists for this trigger type
            if (in_array($trigger_type, $existing_trigger_types, true)) {
                Logger::log(
                    'Webflow webhook already exists, skipping',
                    'info',
                    [
                        'site_id' => $site_id,
                        'trigger_type' => $trigger_type,
                        'webhook_url' => $webhook_url,
                    ]
                );
                continue;
            }

            // API v2.0.0: POST /v2/sites/{site_id}/webhooks
            $url = self::API_BASE_URL . '/v2/sites/' . $site_id . '/webhooks';

            $response = wp_remote_post(
                $url,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-API-Version' => '2.0.0', // Explicitly specify API version
                    ],
                    'body' => wp_json_encode([
                        'url' => $webhook_url,
                        'triggerType' => $trigger_type,
                    ]),
                ]
            );

            if (is_wp_error($response)) {
                Logger::log(
                    'Webflow webhook creation failed',
                    'error',
                    [
                        'site_id' => $site_id,
                        'trigger_type' => $trigger_type,
                        'error' => $response->get_error_message(),
                    ]
                );
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code < 200 || $response_code >= 300) {
                $body = wp_remote_retrieve_body($response);
                $error_data = json_decode($body, true);
                
                // Store error for summary logging
                $error_message = is_array($error_data) 
                    ? (isset($error_data['message']) ? $error_data['message'] : (isset($error_data['msg']) ? $error_data['msg'] : wp_json_encode($error_data)))
                    : $error_data;
                $errors[] = sprintf('%s: %s (HTTP %d)', $trigger_type, $error_message, $response_code);
                
                Logger::log(
                    'Webflow webhook creation failed',
                    'error',
                    [
                        'site_id' => $site_id,
                        'trigger_type' => $trigger_type,
                        'response_code' => $response_code,
                        'error' => $error_data,
                        'webhook_url' => $webhook_url,
                    ]
                );
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $webhook_data = json_decode($body, true);

            // API v2.0.0 webhook response structure
            $webhook_id = $webhook_data['_id'] ?? $webhook_data['id'] ?? null;
            if (!empty($webhook_id)) {
                $created_webhooks[] = [
                    'id' => sanitize_text_field($webhook_id),
                    'trigger_type' => $trigger_type,
                    'url' => isset($webhook_data['url']) ? esc_url_raw($webhook_data['url']) : $webhook_url,
                ];

                Logger::log(
                    'Webflow webhook created',
                    'success',
                    [
                        'site_id' => $site_id,
                        'trigger_type' => $trigger_type,
                        'webhook_id' => $webhook_id,
                    ]
                );
            } else {
                Logger::log(
                    'Webflow webhook creation response invalid',
                    'error',
                    [
                        'site_id' => $site_id,
                        'trigger_type' => $trigger_type,
                        'response' => $webhook_data,
                    ]
                );
            }
        }

        if (empty($created_webhooks)) {
            // Log summary of all failures
            if (!empty($errors)) {
                Logger::log(
                    'Webflow webhook creation: all webhooks failed',
                    'error',
                    [
                        'site_id' => $site_id,
                        'errors' => $errors,
                        'webhook_url' => $webhook_url,
                    ]
                );
            }
            return false;
        }

        // Log summary if some webhooks failed
        if (!empty($errors)) {
            Logger::log(
                'Webflow webhook creation: partial success',
                'warning',
                [
                    'site_id' => $site_id,
                    'created' => count($created_webhooks),
                    'failed' => count($errors),
                    'errors' => $errors,
                ]
            );
        }

        return [
            'webhooks' => $created_webhooks,
            'site_id' => $site_id,
        ];
    }

    /**
     * Get webhook details.
     *
     * @param string $store_identifier Store identifier (site ID).
     * @param array<string, mixed> $oauth_data OAuth data.
     * @return array<string, mixed>|false Webhook data on success, false on failure.
     */
    public function get_webhooks(string $store_identifier, array $oauth_data): array|false {
        if (empty($oauth_data['access_token'])) {
            return false;
        }

        $access_token = $oauth_data['access_token'];
        $site_id = sanitize_text_field($store_identifier);
        // API v2.0.0: GET /v2/sites/{site_id}/webhooks
        $url = self::API_BASE_URL . '/v2/sites/' . $site_id . '/webhooks';

        $response = wp_remote_get(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json',
                    'X-API-Version' => '2.0.0', // Explicitly specify API version
                ],
            ]
        );

        if (is_wp_error($response)) {
            Logger::log('Webflow get webhooks request failed', 'error', ['error' => $response->get_error_message()]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            Logger::log('Webflow get webhooks request failed', 'error', ['response_code' => $response_code]);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // API v2.0.0 response structure: array of webhook objects or { "webhooks": [...] }
        if (empty($data)) {
            return false;
        }

        // Handle both direct array and wrapped response
        if (isset($data['webhooks']) && is_array($data['webhooks'])) {
            return $data['webhooks'];
        }

        if (is_array($data)) {
            return $data;
        }

        return false;
    }

    /**
     * Get OAuth client ID.
     * Checks constants first (wp-config.php), then falls back to options.
     *
     * @return string
     */
    private function get_client_id(): string {
        // Check for constant first (configured in wp-config.php).
        if (defined('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID')) {
            return CAPACITY_TSHIRTS_WEBFLOW_CLIENT_ID;
        }

        // Fall back to option (for backward compatibility).
        return get_option('capacity_tshirts_webflow_client_id', '');
    }

    /**
     * Get OAuth client secret.
     * Checks constants first (wp-config.php), then falls back to options.
     *
     * @return string
     */
    private function get_client_secret(): string {
        // Check for constant first (configured in wp-config.php).
        if (defined('CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET')) {
            return CAPACITY_TSHIRTS_WEBFLOW_CLIENT_SECRET;
        }

        // Fall back to option (for backward compatibility).
        return get_option('capacity_tshirts_webflow_client_secret', '');
    }

    /**
     * Get OAuth redirect URI.
     *
     * @return string
     */
    private function get_redirect_uri(): string {
        return admin_url('admin.php?page=capacity-tshirts-stores-oauth-callback');
    }

    /**
     * Get webhook callback URL.
     *
     * @return string
     */
    private function get_webhook_callback_url(): string {
        return rest_url('capacity-tshirts-stores/v1/webhook');
    }

    /**
     * Update order via Webflow API.
     *
     * @param string $site_id Site ID (store identifier).
     * @param string $order_id Order ID (platform_order_id).
     * @param array<string, mixed> $oauth_data OAuth data.
     * @param array<string, mixed> $update_data Data to update (comment, shippingProvider, shippingTracking, shippingTrackingURL).
     * @return array<string, mixed>|false Updated order data on success, false on failure.
     */
    public function update_order(string $site_id, string $order_id, array $oauth_data, array $update_data): array|false {
        if (empty($oauth_data['access_token'])) {
            Logger::log('Webflow update order: missing access token', 'error', ['oauth_data_keys' => array_keys($oauth_data)]);
            return false;
        }

        $access_token = $oauth_data['access_token'];
        $site_id = sanitize_text_field($site_id);
        $order_id = sanitize_text_field($order_id);

        // Build request body with only provided fields.
        $body = [];
        if (isset($update_data['comment'])) {
            $body['comment'] = sanitize_textarea_field($update_data['comment']);
        }
        if (isset($update_data['shippingProvider'])) {
            $body['shippingProvider'] = sanitize_text_field($update_data['shippingProvider']);
        }
        if (isset($update_data['shippingTracking'])) {
            $body['shippingTracking'] = sanitize_text_field($update_data['shippingTracking']);
        }
        if (isset($update_data['shippingTrackingURL'])) {
            $body['shippingTrackingURL'] = esc_url_raw($update_data['shippingTrackingURL']);
        }

        // API v2: PATCH /v2/sites/{site_id}/orders/{order_id}
        $url = self::API_BASE_URL . '/v2/sites/' . $site_id . '/orders/' . $order_id;

        $response = wp_remote_request(
            $url,
            [
                'method' => 'PATCH',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            Logger::log('Webflow update order request failed', 'error', [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            
            // Check if error is about missing scopes (403 with OAuthForbidden)
            $error_message = '';
            $error_msg = '';
            
            if ($response_code === 403) {
                // Handle different error response formats
                if (is_array($error_data)) {
                    $error_msg = $error_data['msg'] ?? $error_data['message'] ?? $error_data['error'] ?? '';
                } elseif (is_string($error_data)) {
                    $error_msg = $error_data;
                }
                
                // Also check the raw body as fallback
                if (empty($error_msg)) {
                    $error_msg = $body;
                }
                
                // Check for scope-related errors
                if (stripos($error_msg, 'OAuthForbidden') !== false || 
                    (stripos($error_msg, 'missing') !== false && stripos($error_msg, 'scopes') !== false) ||
                    (stripos($error_msg, 'ecommerce:write') !== false && stripos($error_msg, 'missing') !== false)) {
                    $error_message = 'MISSING_SCOPES';
                }
            }
            
            Logger::log('Webflow update order request failed', 'error', [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'response_code' => $response_code,
                'error' => $error_data,
            ]);
            
            // Return array with error type if it's a scope issue
            if ($error_message === 'MISSING_SCOPES') {
                return ['error' => 'MISSING_SCOPES', 'message' => $error_msg ?? 'Missing required OAuth scopes'];
            }
            
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $order_data = json_decode($body, true);

        Logger::log('Webflow order updated successfully', 'success', [
            'site_id' => $site_id,
            'order_id' => $order_id,
            'updated_fields' => array_keys($update_data),
        ]);

        return $order_data;
    }

    /**
     * Fulfill order via Webflow API.
     *
     * @param string $site_id Site ID (store identifier).
     * @param string $order_id Order ID (platform_order_id).
     * @param array<string, mixed> $oauth_data OAuth data.
     * @param bool $send_email Whether to send order fulfilled email.
     * @return array<string, mixed>|false Updated order data on success, false on failure.
     */
    public function fulfill_order(string $site_id, string $order_id, array $oauth_data, bool $send_email = true): array|false {
        if (empty($oauth_data['access_token'])) {
            Logger::log('Webflow fulfill order: missing access token', 'error', ['oauth_data_keys' => array_keys($oauth_data)]);
            return false;
        }

        $access_token = $oauth_data['access_token'];
        $site_id = sanitize_text_field($site_id);
        $order_id = sanitize_text_field($order_id);

        // API v2: POST /v2/sites/{site_id}/orders/{order_id}/fulfill
        $url = self::API_BASE_URL . '/v2/sites/' . $site_id . '/orders/' . $order_id . '/fulfill';

        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'sendOrderFulfilledEmail' => $send_email,
                ]),
            ]
        );

        if (is_wp_error($response)) {
            Logger::log('Webflow fulfill order request failed', 'error', [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            Logger::log('Webflow fulfill order request failed', 'error', [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'response_code' => $response_code,
                'error' => $error_data,
            ]);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $order_data = json_decode($body, true);

        Logger::log('Webflow order fulfilled successfully', 'success', [
            'site_id' => $site_id,
            'order_id' => $order_id,
        ]);

        return $order_data;
    }

    /**
     * Unfulfill order via Webflow API.
     *
     * @param string $site_id Site ID (store identifier).
     * @param string $order_id Order ID (platform_order_id).
     * @param array<string, mixed> $oauth_data OAuth data.
     * @return array<string, mixed>|false Updated order data on success, false on failure.
     */
    public function unfulfill_order(string $site_id, string $order_id, array $oauth_data): array|false {
        if (empty($oauth_data['access_token'])) {
            Logger::log('Webflow unfulfill order: missing access token', 'error', ['oauth_data_keys' => array_keys($oauth_data)]);
            return false;
        }

        $access_token = $oauth_data['access_token'];
        $site_id = sanitize_text_field($site_id);
        $order_id = sanitize_text_field($order_id);

        // API v2: POST /v2/sites/{site_id}/orders/{order_id}/unfulfill
        $url = self::API_BASE_URL . '/v2/sites/' . $site_id . '/orders/' . $order_id . '/unfulfill';

        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            Logger::log('Webflow unfulfill order request failed', 'error', [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            Logger::log('Webflow unfulfill order request failed', 'error', [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'response_code' => $response_code,
                'error' => $error_data,
            ]);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $order_data = json_decode($body, true);

        Logger::log('Webflow order unfulfilled successfully', 'success', [
            'site_id' => $site_id,
            'order_id' => $order_id,
        ]);

        return $order_data;
    }

    /**
     * Refund order via Webflow API.
     *
     * @param string $site_id Site ID (store identifier).
     * @param string $order_id Order ID (platform_order_id).
     * @param array<string, mixed> $oauth_data OAuth data.
     * @param string|null $reason Refund reason (duplicate, fraudulent, requested).
     * @return array<string, mixed>|false Updated order data on success, false on failure.
     */
    public function refund_order(string $site_id, string $order_id, array $oauth_data, ?string $reason = null): array|false {
        if (empty($oauth_data['access_token'])) {
            Logger::log('Webflow refund order: missing access token', 'error', ['oauth_data_keys' => array_keys($oauth_data)]);
            return false;
        }

        $access_token = $oauth_data['access_token'];
        $site_id = sanitize_text_field($site_id);
        $order_id = sanitize_text_field($order_id);

        // API v2: POST /v2/sites/{site_id}/orders/{order_id}/refund
        $url = self::API_BASE_URL . '/v2/sites/' . $site_id . '/orders/' . $order_id . '/refund';

        $body_data = [];
        if ($reason !== null && in_array($reason, ['duplicate', 'fraudulent', 'requested'], true)) {
            $body_data['reason'] = sanitize_text_field($reason);
        }

        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => !empty($body_data) ? wp_json_encode($body_data) : '',
            ]
        );

        if (is_wp_error($response)) {
            Logger::log('Webflow refund order request failed', 'error', [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            Logger::log('Webflow refund order request failed', 'error', [
                'site_id' => $site_id,
                'order_id' => $order_id,
                'response_code' => $response_code,
                'error' => $error_data,
            ]);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $order_data = json_decode($body, true);

        Logger::log('Webflow order refunded successfully', 'success', [
            'site_id' => $site_id,
            'order_id' => $order_id,
            'reason' => $reason,
        ]);

        return $order_data;
    }
}


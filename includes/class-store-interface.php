<?php
/**
 * Store interface.
 *
 * @package CapacityTShirtsStores
 * @subpackage Integrations
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Integrations;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Store interface.
 */
interface Store_Interface {

    /**
     * Get store type identifier.
     *
     * @return string
     */
    public function get_type(): string;

    /**
     * Get OAuth authorization URL.
     *
     * @param array<string, mixed> $args Additional arguments.
     * @return string
     */
    public function get_oauth_url(array $args = []): string;

    /**
     * Handle OAuth callback.
     *
     * @param array<string, mixed> $data OAuth callback data.
     * @return array<string, mixed>|false Access token data on success, false on failure.
     */
    public function handle_oauth_callback(array $data): array|false;

    /**
     * Get stores from the connected account.
     *
     * @param array<string, mixed> $oauth_data OAuth data.
     * @return array<int, array<string, mixed>>
     */
    public function get_stores(array $oauth_data): array;

    /**
     * Create webhooks for a store.
     *
     * @param string $store_identifier Store identifier.
     * @param array<string, mixed> $oauth_data OAuth data.
     * @return array<string, mixed>|false Webhook data on success, false on failure.
     */
    public function create_webhooks(string $store_identifier, array $oauth_data): array|false;

    /**
     * Get webhook details.
     *
     * @param string $store_identifier Store identifier.
     * @param array<string, mixed> $oauth_data OAuth data.
     * @return array<string, mixed>|false Webhook data on success, false on failure.
     */
    public function get_webhooks(string $store_identifier, array $oauth_data): array|false;
}


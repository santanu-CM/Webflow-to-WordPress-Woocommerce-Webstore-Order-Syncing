<?php
/**
 * Centralized logging class.
 *
 * @package CapacityTShirtsStores
 * @subpackage Core
 */

declare(strict_types=1);

namespace CapacityTShirtsStores\Core;

use CapacityTShirtsStores\Database\Repository\Log_Repository;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class.
 */
class Logger {

    /**
     * Log repository instance.
     *
     * @var Log_Repository|null
     */
    private static ?Log_Repository $repository = null;

    /**
     * Log an event.
     *
     * @param string $message Log message.
     * @param string $status Log status (info, success, warning, error).
     * @param array<string, mixed> $context Additional context data.
     * @param int|null $store_id Optional store ID.
     * @return int|false Log ID on success, false on failure.
     */
    public static function log(
        string $message,
        string $status = 'info',
        array $context = [],
        ?int $store_id = null
    ): int|false {
        $repository = self::get_repository();

        // Determine event type from context or default.
        $event_type = $context['event_type'] ?? 'general';
        unset($context['event_type']);

        // Check for duplicate log within last 2 seconds (same message, store_id, and event_type)
        $duplicate_check = $repository->check_duplicate($message, $store_id, $event_type, 2);
        if ($duplicate_check) {
            // Skip duplicate log entry
            return false;
        }

        $data = [
            'store_id' => $store_id,
            'event_type' => $event_type,
            'payload' => [
                'message' => sanitize_text_field($message),
                'context' => $context,
            ],
            'status' => self::sanitize_status($status),
        ];

        return $repository->create($data);
    }

    /**
     * Log OAuth attempt.
     *
     * @param string $store_type Store type.
     * @param bool $success Whether OAuth was successful.
     * @param array<string, mixed> $context Additional context.
     * @param int|null $store_id Optional store ID.
     * @return int|false Log ID on success, false on failure.
     */
    public static function log_oauth(
        string $store_type,
        bool $success,
        array $context = [],
        ?int $store_id = null
    ): int|false {
        $status = $success ? 'success' : 'error';
        $message = sprintf(
            'OAuth attempt for %s store: %s',
            $store_type,
            $success ? 'Success' : 'Failed'
        );

        $context['event_type'] = 'oauth_attempt';
        $context['store_type'] = $store_type;

        return self::log($message, $status, $context, $store_id);
    }

    /**
     * Log webhook creation.
     *
     * @param string $store_type Store type.
     * @param string $store_identifier Store identifier.
     * @param bool $success Whether creation was successful.
     * @param array<string, mixed> $context Additional context.
     * @param int|null $store_id Optional store ID.
     * @return int|false Log ID on success, false on failure.
     */
    public static function log_webhook_creation(
        string $store_type,
        string $store_identifier,
        bool $success,
        array $context = [],
        ?int $store_id = null
    ): int|false {
        $status = $success ? 'success' : 'error';
        $message = sprintf(
            'Webhook creation for %s store %s: %s',
            $store_type,
            $store_identifier,
            $success ? 'Success' : 'Failed'
        );

        $context['event_type'] = 'webhook_creation';
        $context['store_type'] = $store_type;
        $context['store_identifier'] = $store_identifier;

        return self::log($message, $status, $context, $store_id);
    }

    /**
     * Log webhook callback.
     *
     * @param string $store_type Store type.
     * @param string $event_type Event type.
     * @param array<string, mixed> $payload Webhook payload.
     * @param int|null $store_id Optional store ID.
     * @return int|false Log ID on success, false on failure.
     */
    public static function log_webhook_callback(
        string $store_type,
        string $event_type,
        array $payload = [],
        ?int $store_id = null
    ): int|false {
        $message = sprintf(
            'Webhook callback received: %s from %s store',
            $event_type,
            $store_type
        );

        $context = [
            'event_type' => 'webhook_callback',
            'store_type' => $store_type,
            'webhook_event_type' => $event_type,
            'payload' => $payload,
        ];

        return self::log($message, 'info', $context, $store_id);
    }

    /**
     * Get log repository.
     *
     * @return Log_Repository
     */
    private static function get_repository(): Log_Repository {
        if (self::$repository === null) {
            self::$repository = new Log_Repository();
        }

        return self::$repository;
    }

    /**
     * Sanitize log status.
     *
     * @param string $status Status to sanitize.
     * @return string
     */
    private static function sanitize_status(string $status): string {
        $allowed = ['info', 'success', 'warning', 'error'];
        return in_array($status, $allowed, true) ? $status : 'info';
    }
}


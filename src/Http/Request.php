<?php

declare(strict_types=1);

namespace UupCode\Utilities\Http;

/**
 * Static facade for safe, sanitized HTTP input.
 *
 * Usage:
 *
 *   Request::input('name');              // sanitize_text_field from $_REQUEST
 *   Request::string('name', '');
 *   Request::int('page', 1);
 *   Request::float('price', 0.0);
 *   Request::bool('enabled', false);
 *   Request::array('ids', []);
 *
 *   Request::fromPost('name');
 *   Request::fromGet('name');
 *
 *   Request::has('name');
 *   Request::missing('name');
 *   Request::filled('name');
 *
 *   Request::only('name', 'email');
 *   Request::except('_wpnonce');
 *   Request::all();
 *
 *   Request::method();
 *   Request::isPost();
 *   Request::isAjax();
 *
 *   // Throws \RuntimeException on failure
 *   Request::verifyNonce('my-action');
 *   Request::verifyNonce('my-action', 'my_nonce_field');
 */
final class Request
{
    // ─── Typed reads from $_REQUEST ───────────────────────────────────────────

    public static function input(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $raw = $_REQUEST[ $key ] ?? null;
        if ($raw === null) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $raw));
    }

    public static function string(string $key, string $default = ''): string
    {
        return self::input($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $raw = $_REQUEST[ $key ] ?? null;
        if ($raw === null) {
            return $default;
        }
        return absint($raw);
    }

    public static function float(string $key, float $default = 0.0): float
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $raw = $_REQUEST[ $key ] ?? null;
        if ($raw === null) {
            return $default;
        }
        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);
        return $value !== false ? $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $raw = $_REQUEST[ $key ] ?? null;
        if ($raw === null) {
            return $default;
        }
        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Return an array from the request, sanitizing each element as a string.
     *
     * @param  array<string> $default
     * @return list<string>
     */
    public static function array(string $key, array $default = []): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $raw = $_REQUEST[ $key ] ?? null;
        if (! is_array($raw)) {
            return $default;
        }
        return array_map(
            fn ($v) => sanitize_text_field(wp_unslash((string) $v)),
            $raw
        );
    }

    // ─── Source-specific reads ────────────────────────────────────────────────

    public static function fromPost(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $raw = $_POST[ $key ] ?? null;
        if ($raw === null) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $raw));
    }

    public static function fromGet(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $raw = $_GET[ $key ] ?? null;
        if ($raw === null) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $raw));
    }

    // ─── Presence checks ─────────────────────────────────────────────────────

    public static function has(string $key): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        return isset($_REQUEST[ $key ]);
    }

    public static function missing(string $key): bool
    {
        return ! self::has($key);
    }

    public static function filled(string $key): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        return isset($_REQUEST[ $key ]) && $_REQUEST[ $key ] !== '';
    }

    // ─── Subset / all ─────────────────────────────────────────────────────────

    /**
     * Return a sanitized associative array for the given keys.
     *
     * @return array<string, string>
     */
    public static function only(string ...$keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[ $key ] = self::input($key);
        }
        return $result;
    }

    /**
     * Return all sanitized request data, excluding the specified keys.
     *
     * @return array<string, string>
     */
    public static function except(string ...$keys): array
    {
        $all    = self::all();
        $exclude = array_flip($keys);
        return array_diff_key($all, $exclude);
    }

    /**
     * Return all sanitized request data as a string-keyed array.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $result = [];
        foreach ($_REQUEST as $key => $value) {
            if (is_array($value)) {
                $result[ $key ] = array_map(
                    fn ($v) => sanitize_text_field(wp_unslash((string) $v)),
                    $value
                );
            } else {
                $result[ $key ] = sanitize_text_field(wp_unslash((string) $value));
            }
        }
        return $result;
    }

    // ─── Request meta ─────────────────────────────────────────────────────────

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isAjax(): bool
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    // ─── Nonce verification ───────────────────────────────────────────────────

    /**
     * Verify a nonce, throwing \RuntimeException on failure.
     *
     * @throws \RuntimeException When nonce verification fails.
     */
    public static function verifyNonce(string $action, string $field = '_wpnonce'): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $nonce = $_REQUEST[ $field ] ?? '';

        if (! wp_verify_nonce($nonce, $action)) {
            throw new \RuntimeException(
                sprintf('Nonce verification failed for action "%s".', $action)
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Current-user and user-data helpers.
 *
 * Usage:
 *
 *   User::id();
 *   User::current();
 *   User::isLoggedIn();
 *   User::can('edit_posts');
 *   User::can('edit_post', $postId);
 *   User::get($userId);
 *   User::email();
 *   User::displayName();
 */
final class User
{
    /**
     * ID of the currently logged-in user, 0 if not logged in.
     */
    public static function id(): int
    {
        return get_current_user_id();
    }

    /**
     * WP_User object for the currently logged-in user.
     */
    public static function current(): \WP_User
    {
        return wp_get_current_user();
    }

    /**
     * Whether the current visitor is logged in.
     */
    public static function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Check a capability for the current user, optionally with object context.
     *
     * @param mixed ...$args  Extra args passed to current_user_can (e.g. post ID)
     */
    public static function can(string $capability, mixed ...$args): bool
    {
        return current_user_can($capability, ...$args);
    }

    /**
     * Get a WP_User by ID, or false if not found.
     */
    public static function get(int $userId): \WP_User|false
    {
        return get_userdata($userId);
    }

    /**
     * Email address of the currently logged-in user.
     */
    public static function email(): string
    {
        return (string) self::current()->user_email;
    }

    /**
     * Display name of the currently logged-in user.
     */
    public static function displayName(): string
    {
        return (string) self::current()->display_name;
    }
}

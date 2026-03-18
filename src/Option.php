<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Static facade for the WordPress Options & Transients API.
 *
 * Usage:
 *
 *   // Basic get / set / delete
 *   Option::set('my_key', 'hello');
 *   Option::get('my_key', 'default');
 *   Option::delete('my_key');
 *   Option::has('my_key');
 *
 *   // Typed getters
 *   Option::string('my_key', '');
 *   Option::int('my_key', 0);
 *   Option::bool('my_key', false);
 *   Option::array('my_key', []);
 *   Option::float('my_key', 0.0);
 *
 *   // Plugin-scoped prefix
 *   $opt = Option::for('my_plugin');
 *   $opt->set('setting', 'value');      // → update_option('my_plugin_setting', 'value')
 *   $opt->get('setting');
 *   $opt->all();                         // all options whose name starts with prefix
 *
 *   // Transients
 *   Option::setTransient('my_key', $value, 3600);
 *   Option::getTransient('my_key', 'default');
 *   Option::deleteTransient('my_key');
 *   Option::rememberTransient('my_key', fn() => expensive_call(), 3600);
 */
final class Option
{
    // ─── Basic get / set / delete ─────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        return get_option($key, $default);
    }

    public static function set(string $key, mixed $value, bool $autoload = true): bool
    {
        return update_option($key, $value, $autoload);
    }

    public static function delete(string $key): bool
    {
        return delete_option($key);
    }

    public static function has(string $key): bool
    {
        return get_option($key) !== false;
    }

    // ─── Typed getters ────────────────────────────────────────────────────────

    public static function string(string $key, string $default = ''): string
    {
        return (string) get_option($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) get_option($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = get_option($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<mixed> $default
     * @return array<mixed>
     */
    public static function array(string $key, array $default = []): array
    {
        $value = get_option($key, $default);
        return is_array($value) ? $value : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        return (float) get_option($key, $default);
    }

    // ─── Plugin-scoped prefix ─────────────────────────────────────────────────

    public static function for(string $prefix): ScopedOption
    {
        return new ScopedOption(rtrim($prefix, '_') . '_');
    }

    // ─── Transients ───────────────────────────────────────────────────────────

    public static function setTransient(string $key, mixed $value, int $expiration = 0): bool
    {
        return set_transient($key, $value, $expiration);
    }

    public static function getTransient(string $key, mixed $default = null): mixed
    {
        $value = get_transient($key);
        return $value === false ? $default : $value;
    }

    public static function deleteTransient(string $key): bool
    {
        return delete_transient($key);
    }

    /**
     * Get a transient value, or compute, store, and return it.
     */
    public static function rememberTransient(string $key, callable $callback, int $expiration = 0): mixed
    {
        $value = get_transient($key);
        if ($value !== false) {
            return $value;
        }

        $value = $callback();
        set_transient($key, $value, $expiration);
        return $value;
    }
}

/**
 * Scoped option proxy — all keys are prefixed automatically.
 */
final class ScopedOption
{
    public function __construct(private readonly string $prefix)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return get_option($this->prefix . $key, $default);
    }

    public function set(string $key, mixed $value, bool $autoload = true): bool
    {
        return update_option($this->prefix . $key, $value, $autoload);
    }

    public function delete(string $key): bool
    {
        return delete_option($this->prefix . $key);
    }

    public function has(string $key): bool
    {
        return get_option($this->prefix . $key) !== false;
    }

    /**
     * Return all options whose `option_name` starts with this prefix.
     *
     * @return array<string, mixed>  Keyed by the full option_name.
     */
    public function all(): array
    {
        global $wpdb;

        $like = $wpdb->esc_like($this->prefix) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );

        $result = [];
        foreach ($rows as $row) {
            $result[ $row->option_name ] = maybe_unserialize($row->option_value);
        }
        return $result;
    }
}

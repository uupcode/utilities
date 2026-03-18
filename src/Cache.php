<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Static facade for the WordPress Object Cache.
 *
 * Usage:
 *
 *   Cache::get('key', 'group', 'default');
 *   Cache::set('key', $value, 'group', 3600);
 *   Cache::delete('key', 'group');
 *   Cache::has('key', 'group');
 *
 *   // Get-or-compute
 *   Cache::remember('key', 'group', 3600, fn() => db_query());
 *
 *   // Grouped interface (avoids repeating $group)
 *   $c = Cache::group('my_plugin');
 *   $c->get('posts');
 *   $c->set('posts', $data, 600);
 *   $c->delete('posts');
 *   $c->remember('posts', 600, fn() => fetch_posts());
 *   $c->flush();
 *
 *   // Batch
 *   Cache::getMany(['key1', 'key2'], 'group');
 *   Cache::setMany(['key1' => $v1, 'key2' => $v2], 'group', 600);
 *   Cache::deleteMany(['key1', 'key2'], 'group');
 */
final class Cache
{
    // ─── Basic operations ─────────────────────────────────────────────────────

    public static function get(string $key, string $group = '', mixed $default = null): mixed
    {
        $found = false;
        $value = wp_cache_get($key, $group, false, $found);
        return $found ? $value : $default;
    }

    public static function set(string $key, mixed $value, string $group = '', int $ttl = 0): bool
    {
        return wp_cache_set($key, $value, $group, $ttl);
    }

    public static function delete(string $key, string $group = ''): bool
    {
        return wp_cache_delete($key, $group);
    }

    public static function has(string $key, string $group = ''): bool
    {
        $found = false;
        wp_cache_get($key, $group, false, $found);
        return $found;
    }

    // ─── Remember ─────────────────────────────────────────────────────────────

    /**
     * Get a cached value or compute, store, and return it.
     */
    public static function remember(string $key, string $group, int $ttl, callable $callback): mixed
    {
        $found = false;
        $value = wp_cache_get($key, $group, false, $found);
        if ($found) {
            return $value;
        }

        $value = $callback();
        wp_cache_set($key, $value, $group, $ttl);
        return $value;
    }

    // ─── Grouped interface ────────────────────────────────────────────────────

    public static function group(string $group): GroupedCache
    {
        return new GroupedCache($group);
    }

    // ─── Batch ────────────────────────────────────────────────────────────────

    /**
     * Retrieve multiple cache keys at once.
     *
     * @param  list<string>           $keys
     * @return array<string, mixed>   Keyed by cache key; missing entries are absent.
     */
    public static function getMany(array $keys, string $group = ''): array
    {
        if (function_exists('wp_cache_get_multiple')) {
            $results = wp_cache_get_multiple($keys, $group);
            // wp_cache_get_multiple returns false for missing; filter those out.
            return array_filter($results, fn ($v) => $v !== false);
        }

        $result = [];
        foreach ($keys as $key) {
            $found = false;
            $value = wp_cache_get($key, $group, false, $found);
            if ($found) {
                $result[ $key ] = $value;
            }
        }
        return $result;
    }

    /**
     * Store multiple key/value pairs at once.
     *
     * @param array<string, mixed> $data
     */
    public static function setMany(array $data, string $group = '', int $ttl = 0): bool
    {
        if (function_exists('wp_cache_set_multiple')) {
            $results = wp_cache_set_multiple($data, $group, $ttl);
            return ! in_array(false, $results, true);
        }

        $success = true;
        foreach ($data as $key => $value) {
            if (! wp_cache_set($key, $value, $group, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Delete multiple cache keys at once.
     *
     * @param list<string> $keys
     */
    public static function deleteMany(array $keys, string $group = ''): bool
    {
        if (function_exists('wp_cache_delete_multiple')) {
            $results = wp_cache_delete_multiple($keys, $group);
            return ! in_array(false, $results, true);
        }

        $success = true;
        foreach ($keys as $key) {
            if (! wp_cache_delete($key, $group)) {
                $success = false;
            }
        }
        return $success;
    }
}

/**
 * Cache instance scoped to a single group — avoids repeating the group argument.
 */
final class GroupedCache
{
    public function __construct(private readonly string $group)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $this->group, $default);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return Cache::set($key, $value, $this->group, $ttl);
    }

    public function delete(string $key): bool
    {
        return Cache::delete($key, $this->group);
    }

    public function has(string $key): bool
    {
        return Cache::has($key, $this->group);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $this->group, $ttl, $callback);
    }

    /**
     * Flush all keys in this group.
     * Requires a persistent object cache that supports group flushing (e.g. Redis).
     * Falls back to a no-op on the default non-persistent cache.
     */
    public function flush(): bool
    {
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group($this->group);
        }
        return false;
    }
}

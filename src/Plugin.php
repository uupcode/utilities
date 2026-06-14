<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Plugin lifecycle and path helpers.
 *
 * Call Plugin::boot(__FILE__) once in your plugin root, then use the
 * static methods anywhere in your plugin.
 *
 * Usage:
 *
 *   Plugin::boot(__FILE__);
 *
 *   Plugin::onActivate(fn() => install_tables());
 *   Plugin::onDeactivate(fn() => flush_rewrite_rules());
 *   Plugin::onUninstall(fn() => drop_tables());
 *
 *   Plugin::path('assets/js/app.js');
 *   Plugin::url('assets/js/app.js');
 *   Plugin::version();
 *   Plugin::basename();
 *   Plugin::isActive('woocommerce/woocommerce.php');
 *
 * ── Multiple plugins, one class ─────────────────────────────────────────────
 * When two plugins each ship this library under the same `UupCode\Utilities`
 * namespace, PHP loads ONE copy of this class and its statics are shared. A
 * single `$file` static would therefore be clobbered by whichever plugin boots
 * last, so every `path()/url()` call would resolve against the wrong directory.
 *
 * Instead we keep a registry of every booted plugin (keyed by its directory) and
 * resolve `path/url/basename/version` to the plugin that OWNS the calling file —
 * found by walking the backtrace to the first frame outside this library and
 * matching it against a registered plugin directory. Single-plugin consumers are
 * unaffected (the registry has one entry and the fallback returns it).
 */
final class Plugin
{
    /**
     * Booted plugins, keyed by normalized directory path (with trailing slash).
     *
     * @var array<string, array{file: string, path: string, url: string}>
     */
    private static array $plugins = [];

    /** Key of the most recently booted plugin — fallback when the caller can't be resolved. */
    private static string $lastKey = '';

    /**
     * Boot the helper with the plugin's main file path.
     * Must be called before any other method.
     */
    public static function boot(string $file): void
    {
        $path = plugin_dir_path($file);
        $key  = self::normalize($path);

        self::$plugins[$key] = [
            'file' => $file,
            'path' => $path,
            'url'  => plugin_dir_url($file),
        ];
        self::$lastKey = $key;
    }

    /**
     * Register a callback to run on plugin activation.
     */
    public static function onActivate(callable $callback): void
    {
        register_activation_hook(self::current()['file'], $callback);
    }

    /**
     * Register a callback to run on plugin deactivation.
     */
    public static function onDeactivate(callable $callback): void
    {
        register_deactivation_hook(self::current()['file'], $callback);
    }

    /**
     * Register a callback to run on plugin uninstall.
     */
    public static function onUninstall(callable $callback): void
    {
        register_uninstall_hook(self::current()['file'], $callback);
    }

    /**
     * Absolute filesystem path to a file relative to the plugin root.
     */
    public static function path(string $relative = ''): string
    {
        return self::current()['path'] . ltrim($relative, '/');
    }

    /**
     * Public URL to a file relative to the plugin root.
     */
    public static function url(string $relative = ''): string
    {
        return self::current()['url'] . ltrim($relative, '/');
    }

    /**
     * Read the Version header from the plugin file.
     */
    public static function version(): string
    {
        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data(self::current()['file'], false, false);
        return $data['Version'];
    }

    /**
     * Plugin basename, e.g. my-plugin/my-plugin.php.
     */
    public static function basename(): string
    {
        return plugin_basename(self::current()['file']);
    }

    /**
     * Whether another plugin is active.
     */
    public static function isActive(string $pluginBasename): bool
    {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($pluginBasename);
    }

    // ─── Internal ───────────────────────────────────────────────────────────

    /**
     * Resolve the booted plugin that owns the calling code.
     *
     * Walks the backtrace, skips frames inside this library (its own files live
     * under a consumer's vendor/ dir, so they must not match), and returns the
     * first frame whose file sits within a registered plugin directory. Falls
     * back to the most-recently-booted plugin.
     *
     * @return array{file: string, path: string, url: string}
     */
    private static function current(): array
    {
        if (count(self::$plugins) <= 1) {
            return self::$plugins[self::$lastKey] ?? self::empty();
        }

        $libRoot = self::normalize(dirname(__DIR__)); // this library's package root

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (empty($frame['file'])) {
                continue;
            }

            $caller = self::normalize(dirname($frame['file']) . '/');

            // Skip frames inside this library (Plugin.php, ServiceProvider, facades, …).
            if (str_starts_with($caller, $libRoot)) {
                continue;
            }

            foreach (self::$plugins as $key => $plugin) {
                if (str_starts_with($caller, $key)) {
                    return $plugin;
                }
            }
        }

        return self::$plugins[self::$lastKey] ?? self::empty();
    }

    /** @return array{file: string, path: string, url: string} */
    private static function empty(): array
    {
        return ['file' => '', 'path' => '', 'url' => ''];
    }

    private static function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }
}

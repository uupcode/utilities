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
 */
final class Plugin
{
    private static string $file    = '';
    private static string $dirPath = '';
    private static string $dirUrl  = '';

    /**
     * Boot the helper with the plugin's main file path.
     * Must be called before any other method.
     */
    public static function boot(string $file): void
    {
        self::$file    = $file;
        self::$dirPath = plugin_dir_path($file);
        self::$dirUrl  = plugin_dir_url($file);
    }

    /**
     * Register a callback to run on plugin activation.
     */
    public static function onActivate(callable $callback): void
    {
        register_activation_hook(self::$file, $callback);
    }

    /**
     * Register a callback to run on plugin deactivation.
     */
    public static function onDeactivate(callable $callback): void
    {
        register_deactivation_hook(self::$file, $callback);
    }

    /**
     * Register a callback to run on plugin uninstall.
     */
    public static function onUninstall(callable $callback): void
    {
        register_uninstall_hook(self::$file, $callback);
    }

    /**
     * Absolute filesystem path to a file relative to the plugin root.
     */
    public static function path(string $relative = ''): string
    {
        return self::$dirPath . ltrim($relative, '/');
    }

    /**
     * Public URL to a file relative to the plugin root.
     */
    public static function url(string $relative = ''): string
    {
        return self::$dirUrl . ltrim($relative, '/');
    }

    /**
     * Read the Version header from the plugin file.
     */
    public static function version(): string
    {
        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data(self::$file, false, false);
        return $data['Version'];
    }

    /**
     * Plugin basename, e.g. my-plugin/my-plugin.php.
     */
    public static function basename(): string
    {
        return plugin_basename(self::$file);
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
}

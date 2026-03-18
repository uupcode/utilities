<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Template rendering helpers with theme override support.
 *
 * Templates are resolved in priority order:
 *   1. Child theme  → get_stylesheet_directory() . '/templates/{name}.php'
 *   2. Parent theme → get_template_directory()   . '/templates/{name}.php'
 *   3. Plugin root  → configured base path        . '/templates/{name}.php'
 *
 * Configure the plugin base path once:
 *   View::setBasePath(plugin_dir_path(__FILE__));
 *
 * Usage:
 *
 *   View::render('my-plugin/card', ['post' => $post, 'price' => 99]);
 *   $html = View::capture('my-plugin/card', ['post' => $post]);
 *   View::exists('my-plugin/card');
 */
final class View
{
    private static string $basePath = '';

    /**
     * Set the plugin root directory (without trailing slash).
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    /**
     * Render a template directly (echoes output).
     *
     * @param array<string, mixed> $data  Variables extracted into template scope.
     */
    public static function render(string $name, array $data = []): void
    {
        $file = self::locate($name);

        if ($file === null) {
            trigger_error('UupCode\\Utilities\\View: template not found: ' . esc_html($name), E_USER_WARNING);
            return;
        }

        self::include($file, $data);
    }

    /**
     * Capture a template as a string instead of echoing it.
     *
     * @param array<string, mixed> $data
     */
    public static function capture(string $name, array $data = []): string
    {
        $file = self::locate($name);

        if ($file === null) {
            return '';
        }

        ob_start();
        self::include($file, $data);
        return (string) ob_get_clean();
    }

    /**
     * Whether a template file exists in any of the search locations.
     */
    public static function exists(string $name): bool
    {
        return self::locate($name) !== null;
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /**
     * Locate a template file, returning the absolute path or null.
     */
    private static function locate(string $name): ?string
    {
        $relative = 'templates/' . ltrim($name, '/') . '.php';

        // Use locate_template for theme hierarchy (child → parent).
        $themeFile = locate_template($relative);
        if ($themeFile !== '') {
            return $themeFile;
        }

        // Fall back to the plugin's own templates directory.
        if (self::$basePath !== '') {
            $pluginFile = self::$basePath . '/' . $relative;
            if (file_exists($pluginFile)) {
                return $pluginFile;
            }
        }

        return null;
    }

    /**
     * Include the file with extracted variables in an isolated scope.
     *
     * @param array<string, mixed> $data
     */
    private static function include(string $file, array $data): void
    {
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($data, EXTR_SKIP);
        include $file;
    }
}

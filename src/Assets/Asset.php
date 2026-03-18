<?php

declare(strict_types=1);

namespace UupCode\Utilities\Assets;

/**
 * Static facade for script and style enqueuing.
 *
 * Usage:
 *
 *   Asset::script('my-plugin', plugin_dir_url(__FILE__) . 'js/app.js')
 *       ->deps('jquery', 'wp-element')
 *       ->version('1.2.0')
 *       ->footer()
 *       ->localize('myPlugin', ['ajaxUrl' => admin_url('admin-ajax.php')])
 *       ->onlyAdmin()
 *       ->enqueue();
 *
 *   Asset::style('my-plugin-css', plugin_dir_url(__FILE__) . 'css/style.css')
 *       ->deps('wp-components')
 *       ->version('1.2.0')
 *       ->media('screen')
 *       ->onlyFrontend()
 *       ->enqueue();
 *
 *   // Register without enqueue (for conditional use later)
 *   Asset::script('my-plugin', '...')->register();
 *
 *   // Inline additions
 *   Asset::script('my-plugin', '...')->addInline('const cfg = ' . json_encode($cfg) . ';')->enqueue();
 *   Asset::style('my-plugin-css', '...')->addInlineStyle('.foo { color: red; }')->enqueue();
 */
final class Asset
{
    public static function script(string $handle, string $src): ScriptAsset
    {
        return new ScriptAsset($handle, $src);
    }

    public static function style(string $handle, string $src): StyleAsset
    {
        return new StyleAsset($handle, $src);
    }
}

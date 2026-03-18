<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Shortcode registration helpers.
 *
 * Usage:
 *
 *   Shortcode::register('my_btn', function(array $atts, string $content = '') {
 *       $atts = shortcode_atts(['label' => 'Click me', 'url' => '#'], $atts);
 *       return "<a href=\"{$atts['url']}\">{$content}</a>";
 *   });
 *
 *   Shortcode::remove('embed');
 *   Shortcode::exists('my_btn');
 */
final class Shortcode
{
    /**
     * Register a shortcode callback.
     *
     * @param callable $callback  Receives (array $atts, string $content, string $tag) → string
     */
    public static function register(string $tag, callable $callback): void
    {
        add_shortcode($tag, $callback);
    }

    /**
     * Remove a registered shortcode.
     */
    public static function remove(string $tag): void
    {
        remove_shortcode($tag);
    }

    /**
     * Whether a shortcode tag is registered.
     */
    public static function exists(string $tag): bool
    {
        return shortcode_exists($tag);
    }
}

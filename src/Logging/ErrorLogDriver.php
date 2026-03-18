<?php

declare(strict_types=1);

namespace UupCode\Utilities\Logging;

/**
 * Log driver that writes via PHP's error_log().
 *
 * When WP_DEBUG and WP_DEBUG_LOG are both true this flows straight into
 * wp-content/debug.log — no extra configuration required.
 *
 * This is the default driver used when Log::configure() has not been called.
 */
final class ErrorLogDriver implements LogDriver
{
    /**
     * @param array<string, mixed> $context
     */
    public function write(LogLevel $level, string $channel, string $message, array $context): void
    {
        error_log(self::format($level, $channel, $message, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function format(LogLevel $level, string $channel, string $message, array $context): string
    {
        $timestamp = wp_date('Y-m-d H:i:s');
        $ctx       = empty($context) ? '' : ' ' . wp_json_encode($context);
        return "[{$timestamp}] {$channel}.{$level->label()}: {$message}{$ctx}";
    }
}

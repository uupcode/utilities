<?php

declare(strict_types=1);

namespace UupCode\Utilities\Logging;

/**
 * Log driver that appends every entry to a single file.
 *
 * Configuration:
 *   'driver' => 'single',
 *   'path'   => WP_CONTENT_DIR . '/logs/app.log',
 */
final class SingleFileLogDriver implements LogDriver
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function write(LogLevel $level, string $channel, string $message, array $context): void
    {
        $this->ensureDirectory();
        file_put_contents(
            $this->path,
            ErrorLogDriver::format($level, $channel, $message, $context) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
        }
    }
}

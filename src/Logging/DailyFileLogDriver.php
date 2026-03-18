<?php

declare(strict_types=1);

namespace UupCode\Utilities\Logging;

/**
 * Log driver that writes to a date-stamped file and prunes old files.
 *
 * Configuration:
 *   'driver' => 'daily',
 *   'path'   => WP_CONTENT_DIR . '/logs/app.log',  // base path — date is injected
 *   'days'   => 14,                                  // files older than this are deleted
 *
 * Given path = '/logs/app.log', today's file will be '/logs/app-2026-03-18.log'.
 */
final class DailyFileLogDriver implements LogDriver
{
    public function __construct(
        private readonly string $basePath,
        private readonly int    $days = 7,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function write(LogLevel $level, string $channel, string $message, array $context): void
    {
        $path = $this->todayPath();
        $this->ensureDirectory($path);

        file_put_contents(
            $path,
            ErrorLogDriver::format($level, $channel, $message, $context) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        $this->prune();
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function todayPath(): string
    {
        $info    = pathinfo($this->basePath);
        $dir     = $info['dirname'];
        $name    = $info['filename'];
        $ext     = isset($info['extension']) ? '.' . $info['extension'] : '';
        $date    = wp_date('Y-m-d');
        return "{$dir}/{$name}-{$date}{$ext}";
    }

    private function ensureDirectory(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
        }
    }

    /**
     * Delete log files older than $this->days.
     */
    private function prune(): void
    {
        $info    = pathinfo($this->basePath);
        $dir     = $info['dirname'];
        $name    = $info['filename'];
        $ext     = isset($info['extension']) ? '.' . $info['extension'] : '';
        $pattern = "{$dir}/{$name}-*{$ext}";

        $files = glob($pattern);
        if (! is_array($files)) {
            return;
        }

        $cutoff = strtotime("-{$this->days} days");
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}

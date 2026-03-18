<?php

declare(strict_types=1);

namespace UupCode\Utilities;

use UupCode\Utilities\Logging\Log;

/**
 * Development debugging utilities — dump, dd, backtrace, and timers.
 *
 * Usage:
 *
 *   // Pretty-print one or more values to output
 *   Debug::dump($post, $meta);
 *
 *   // Dump and die
 *   Debug::dd($response);
 *
 *   // Send a formatted dump to the log (via Log::debug) instead of screen
 *   Debug::log($value, 'label');
 *
 *   // Print a formatted call stack to output
 *   Debug::trace(depth: 10);
 *
 *   // Send call stack to the log
 *   Debug::traceLog(depth: 10);
 *
 *   // Simple profiling
 *   Debug::startTimer('my-query');
 *   // ... work ...
 *   $ms = Debug::stopTimer('my-query');
 *   Debug::stopTimer('my-query', log: true);  // also writes to Log::debug
 */
final class Debug
{
    /** @var array<string, float> label → microtime(true) */
    private static array $timers = [];

    // ─── Dump ─────────────────────────────────────────────────────────────────

    /**
     * Pretty-print one or more values to output.
     *
     * In a web context each value is wrapped in <pre>; in CLI it is plain text.
     */
    public static function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            echo self::format($var);
        }
    }

    /**
     * Dump one or more values and terminate the request.
     */
    public static function dd(mixed ...$vars): never
    {
        self::dump(...$vars);
        die();
    }

    /**
     * Send a formatted dump to Log::debug() instead of screen output.
     */
    public static function log(mixed $var, string $label = ''): void
    {
        $formatted = print_r($var, true);
        $message   = $label !== '' ? "{$label}: {$formatted}" : $formatted;
        Log::debug($message);
    }

    // ─── Backtrace ────────────────────────────────────────────────────────────

    /**
     * Print a human-readable call stack to output.
     */
    public static function trace(int $depth = 10): void
    {
        echo self::formatTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1), skip: 1);
    }

    /**
     * Send a formatted call stack to Log::debug().
     */
    public static function traceLog(int $depth = 10): void
    {
        $trace   = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1);
        $message = self::formatTrace($trace, skip: 1, html: false);
        Log::debug('Backtrace', [ 'trace' => $message ]);
    }

    // ─── Timers ───────────────────────────────────────────────────────────────

    /**
     * Start a named timer.
     */
    public static function startTimer(string $label): void
    {
        self::$timers[ $label ] = microtime(true);
    }

    /**
     * Stop a named timer and return elapsed milliseconds.
     *
     * @param bool $log  When true, also writes the result to Log::debug().
     * @param bool $echo When true, also prints the result to output.
     */
    public static function stopTimer(string $label, bool $log = false, bool $echo = false): float
    {
        if (! isset(self::$timers[ $label ])) {
            return 0.0;
        }

        $ms = round((microtime(true) - self::$timers[ $label ]) * 1000, 3);
        unset(self::$timers[ $label ]);

        if ($log) {
            Log::debug("Timer [{$label}]", [ 'ms' => $ms ]);
        }

        if ($echo) {
            echo self::isWeb()
                ? "<pre>Timer [{$label}]: {$ms}ms</pre>"
                : "Timer [{$label}]: {$ms}ms\n";
        }

        return $ms;
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private static function format(mixed $var): string
    {
        $output = print_r($var, true);

        if (self::isWeb()) {
            return '<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px 16px;'
                . 'border-radius:4px;font-size:13px;line-height:1.5;overflow:auto;'
                . 'margin:8px 0;text-align:left;">'
                . htmlspecialchars($output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre>';
        }

        return $output . "\n";
    }

    /**
     * @param list<array<string,mixed>> $trace
     */
    private static function formatTrace(array $trace, int $skip = 0, bool $html = true): string
    {
        $frames = array_slice($trace, $skip);
        $lines  = [];

        foreach ($frames as $i => $frame) {
            $file     = isset($frame['file']) ? self::shortenPath((string) $frame['file']) : '[internal]';
            $line     = $frame['line'] ?? '?';
            $function = '';

            if (isset($frame['class'])) {
                $function = $frame['class'] . ($frame['type'] ?? '::') . $frame['function'] . '()';
            } elseif (isset($frame['function'])) {
                $function = $frame['function'] . '()';
            }

            $lines[] = "#{$i}  {$file}:{$line}  {$function}";
        }

        $output = implode("\n", $lines);

        if ($html) {
            return '<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px 16px;'
                . 'border-radius:4px;font-size:13px;line-height:1.5;overflow:auto;'
                . 'margin:8px 0;text-align:left;">'
                . htmlspecialchars($output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre>';
        }

        return $output;
    }

    private static function shortenPath(string $path): string
    {
        // Strip the ABSPATH prefix so paths are readable.
        if (defined('ABSPATH') && str_starts_with($path, ABSPATH)) {
            return '…/' . ltrim(substr($path, strlen(ABSPATH)), '/');
        }
        return $path;
    }

    private static function isWeb(): bool
    {
        return php_sapi_name() !== 'cli';
    }
}

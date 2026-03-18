<?php

declare(strict_types=1);

namespace UupCode\Utilities\Logging;

/**
 * Laravel-inspired static logging facade.
 *
 * Zero-config usage (writes via error_log → WP_DEBUG_LOG):
 *
 *   Log::info('User logged in', ['user_id' => 5]);
 *   Log::error('Payment failed', ['order_id' => 99]);
 *
 * Configure named channels once (e.g. in your plugin's boot hook):
 *
 *   Log::configure([
 *       'default'  => 'app',
 *       'channels' => [
 *           'app' => [
 *               'driver' => 'single',
 *               'path'   => WP_CONTENT_DIR . '/logs/app.log',
 *               'level'  => 'debug',
 *           ],
 *           'payments' => [
 *               'driver' => 'daily',
 *               'path'   => WP_CONTENT_DIR . '/logs/payments.log',
 *               'days'   => 14,
 *               'level'  => 'warning',
 *           ],
 *           'wp_debug' => [
 *               'driver' => 'errorlog',
 *               'level'  => 'debug',
 *           ],
 *       ],
 *   ]);
 *
 * Named channel usage:
 *
 *   Log::channel('payments')->error('Stripe failed', ['payload' => $data]);
 *   Log::channel('daily')->info('Cron finished');
 *
 * Log line format:
 *   [2026-03-18 14:32:01] app.INFO: User logged in {"user_id":5}
 */
final class Log
{
    /** @var array{default?:string, channels?:array<string,array<string,mixed>>} */
    private static array $config = [];

    /** @var array<string, LogChannel> */
    private static array $channels = [];

    // ─── Configuration ────────────────────────────────────────────────────────

    /**
     * Set channel configuration. Call once during plugin boot.
     *
     * @param array{default?:string, channels?:array<string,array<string,mixed>>} $config
     */
    public static function configure(array $config): void
    {
        self::$config   = $config;
        self::$channels = []; // flush cached channel instances
    }

    // ─── Channel resolution ───────────────────────────────────────────────────

    /**
     * Return the named channel instance (or the default if no name is given).
     */
    public static function channel(string $name = ''): LogChannel
    {
        if ($name === '') {
            $name = self::$config['default'] ?? 'default';
        }

        if (! isset(self::$channels[ $name ])) {
            self::$channels[ $name ] = self::resolveChannel($name);
        }

        return self::$channels[ $name ];
    }

    // ─── PSR-3 shortcuts on the default channel ───────────────────────────────

    /** @param array<string, mixed> $context */
    public static function debug(string $message, array $context = []): void
    {
        self::channel()->debug($message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::channel()->info($message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function notice(string $message, array $context = []): void
    {
        self::channel()->notice($message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::channel()->warning($message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::channel()->error($message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function critical(string $message, array $context = []): void
    {
        self::channel()->critical($message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function alert(string $message, array $context = []): void
    {
        self::channel()->alert($message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function emergency(string $message, array $context = []): void
    {
        self::channel()->emergency($message, $context);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private static function resolveChannel(string $name): LogChannel
    {
        $cfg    = self::$config['channels'][ $name ] ?? [];
        $driver = self::resolveDriver($cfg);
        $level  = LogLevel::fromName($cfg['level'] ?? 'debug');

        return new LogChannel($name, $driver, $level);
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function resolveDriver(array $cfg): LogDriver
    {
        return match ($cfg['driver'] ?? 'errorlog') {
            'single'   => new SingleFileLogDriver(
                $cfg['path'] ?? (WP_CONTENT_DIR . '/logs/app.log')
            ),
            'daily'    => new DailyFileLogDriver(
                $cfg['path'] ?? (WP_CONTENT_DIR . '/logs/app.log'),
                (int) ($cfg['days'] ?? 7)
            ),
            'errorlog' => new ErrorLogDriver(),
            default    => new ErrorLogDriver(),
        };
    }
}

/**
 * A resolved channel instance — holds a driver, a name, and a minimum level.
 *
 * Returned by Log::channel('name').
 */
final class LogChannel
{
    public function __construct(
        private readonly string    $name,
        private readonly LogDriver $driver,
        private readonly LogLevel  $minLevel,
    ) {
    }

    // ─── PSR-3 methods ────────────────────────────────────────────────────────

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::Debug, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::Info, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::Notice, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::Warning, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::Error, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::Critical, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::Alert, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::Emergency, $message, $context);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $context */
    private function log(LogLevel $level, string $message, array $context): void
    {
        if ($level->value < $this->minLevel->value) {
            return;
        }

        $this->driver->write($level, $this->name, $message, $context);
    }
}

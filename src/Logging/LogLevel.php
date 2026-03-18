<?php

declare(strict_types=1);

namespace UupCode\Utilities\Logging;

/**
 * PSR-3 log levels as a backed enum.
 *
 * Integer values allow minimum-level comparisons:
 *   $level->value >= LogLevel::Warning->value
 */
enum LogLevel: int
{
    case Debug     = 100;
    case Info      = 200;
    case Notice    = 250;
    case Warning   = 300;
    case Error     = 400;
    case Critical  = 500;
    case Alert     = 550;
    case Emergency = 600;

    /**
     * Resolve a level from a string name (case-insensitive).
     *
     * @throws \ValueError On unknown level name.
     */
    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            'debug'     => self::Debug,
            'info'      => self::Info,
            'notice'    => self::Notice,
            'warning'   => self::Warning,
            'error'     => self::Error,
            'critical'  => self::Critical,
            'alert'     => self::Alert,
            'emergency' => self::Emergency,
            default     => throw new \ValueError("Unknown log level: {$name}"),
        };
    }

    public function label(): string
    {
        return strtoupper($this->name);
    }
}

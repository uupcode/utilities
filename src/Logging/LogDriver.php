<?php

declare(strict_types=1);

namespace UupCode\Utilities\Logging;

/**
 * Contract for all log drivers.
 *
 * Drivers receive a fully-formed log record and write it somewhere.
 * Level filtering is handled upstream by LogChannel — drivers always write.
 */
interface LogDriver
{
    /**
     * @param array<string, mixed> $context
     */
    public function write(LogLevel $level, string $channel, string $message, array $context): void;
}

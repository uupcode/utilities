<?php

declare(strict_types=1);

namespace UupCode\Utilities\Tests\Unit\Logging;

use PHPUnit\Framework\TestCase;
use UupCode\Utilities\Logging\LogLevel;

final class LogLevelTest extends TestCase
{
    public function testFromNameIsCaseInsensitive(): void
    {
        $this->assertSame(LogLevel::Debug, LogLevel::fromName('debug'));
        $this->assertSame(LogLevel::Debug, LogLevel::fromName('DEBUG'));
        $this->assertSame(LogLevel::Error, LogLevel::fromName('Error'));
    }

    public function testFromNameAllLevels(): void
    {
        $cases = [
            'debug'     => LogLevel::Debug,
            'info'      => LogLevel::Info,
            'notice'    => LogLevel::Notice,
            'warning'   => LogLevel::Warning,
            'error'     => LogLevel::Error,
            'critical'  => LogLevel::Critical,
            'alert'     => LogLevel::Alert,
            'emergency' => LogLevel::Emergency,
        ];

        foreach ($cases as $name => $expected) {
            $this->assertSame($expected, LogLevel::fromName($name));
        }
    }

    public function testFromNameThrowsOnUnknown(): void
    {
        $this->expectException(\ValueError::class);
        LogLevel::fromName('unknown');
    }

    public function testLabelReturnsUppercaseName(): void
    {
        $this->assertSame('DEBUG', LogLevel::Debug->label());
        $this->assertSame('ERROR', LogLevel::Error->label());
        $this->assertSame('EMERGENCY', LogLevel::Emergency->label());
    }

    public function testSeverityOrdering(): void
    {
        $this->assertGreaterThan(LogLevel::Debug->value, LogLevel::Info->value);
        $this->assertGreaterThan(LogLevel::Info->value, LogLevel::Notice->value);
        $this->assertGreaterThan(LogLevel::Notice->value, LogLevel::Warning->value);
        $this->assertGreaterThan(LogLevel::Warning->value, LogLevel::Error->value);
        $this->assertGreaterThan(LogLevel::Error->value, LogLevel::Critical->value);
        $this->assertGreaterThan(LogLevel::Critical->value, LogLevel::Alert->value);
        $this->assertGreaterThan(LogLevel::Alert->value, LogLevel::Emergency->value);
    }
}

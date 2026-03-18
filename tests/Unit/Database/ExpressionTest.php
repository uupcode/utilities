<?php

declare(strict_types=1);

namespace UupCode\Utilities\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use UupCode\Utilities\Database\Expression;

final class ExpressionTest extends TestCase
{
    public function testGetValueReturnsRawString(): void
    {
        $expr = new Expression('views + 1');
        $this->assertSame('views + 1', $expr->getValue());
    }

    public function testToStringReturnsRawString(): void
    {
        $expr = new Expression('COUNT(*) AS total');
        $this->assertSame('COUNT(*) AS total', (string) $expr);
    }

    public function testEmptyExpression(): void
    {
        $expr = new Expression('');
        $this->assertSame('', $expr->getValue());
    }
}

<?php

declare(strict_types=1);

namespace UupCode\Utilities\Tests\Unit\Http;

use Brain\Monkey\Functions;
use UupCode\Utilities\Http\Request;
use UupCode\Utilities\Tests\TestCase;

final class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_REQUEST = [];
        $_POST    = [];
        $_GET     = [];
    }

    protected function tearDown(): void
    {
        $_REQUEST = [];
        $_POST    = [];
        $_GET     = [];
        parent::tearDown();
    }

    private function stubSanitize(): void
    {
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
    }

    public function testStringReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertSame('fallback', Request::string('name', 'fallback'));
    }

    public function testStringReturnsSanitizedValue(): void
    {
        $this->stubSanitize();
        $_REQUEST['name'] = 'hello';
        $this->assertSame('hello', Request::string('name'));
    }

    public function testIntReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertSame(5, Request::int('page', 5));
    }

    public function testIntReturnsAbsoluteInteger(): void
    {
        Functions\when('absint')->alias(fn ($v) => abs((int) $v));
        $_REQUEST['page'] = '3';
        $this->assertSame(3, Request::int('page'));
    }

    public function testFloatReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertSame(1.5, Request::float('price', 1.5));
    }

    public function testFloatParsesNumericString(): void
    {
        $_REQUEST['price'] = '9.99';
        $this->assertSame(9.99, Request::float('price'));
    }

    public function testBoolReturnsDefaultWhenMissing(): void
    {
        $this->assertFalse(Request::bool('enabled'));
    }

    public function testBoolParsesTrue(): void
    {
        $_REQUEST['enabled'] = 'true';
        $this->assertTrue(Request::bool('enabled'));
    }

    public function testBoolParsesFalse(): void
    {
        $_REQUEST['enabled'] = 'false';
        $this->assertFalse(Request::bool('enabled'));
    }

    public function testHasReturnsTrueWhenPresent(): void
    {
        $_REQUEST['foo'] = 'bar';
        $this->assertTrue(Request::has('foo'));
    }

    public function testHasReturnsFalseWhenAbsent(): void
    {
        $this->assertFalse(Request::has('missing'));
    }

    public function testMissingIsInverseOfHas(): void
    {
        $_REQUEST['foo'] = 'bar';
        $this->assertFalse(Request::missing('foo'));
        $this->assertTrue(Request::missing('baz'));
    }

    public function testFilledReturnsFalseForEmptyString(): void
    {
        $_REQUEST['empty'] = '';
        $this->assertFalse(Request::filled('empty'));
    }

    public function testFilledReturnsTrueForNonEmptyValue(): void
    {
        $_REQUEST['val'] = 'x';
        $this->assertTrue(Request::filled('val'));
    }

    public function testFilledReturnsFalseWhenKeyAbsent(): void
    {
        $this->assertFalse(Request::filled('absent'));
    }

    public function testMethodDefaultsToGet(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $this->assertSame('GET', Request::method());
    }

    public function testIsPostReturnsTrueForPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(Request::isPost());
    }

    public function testIsAjaxReturnsFalseWhenConstantUndefined(): void
    {
        $this->assertFalse(Request::isAjax());
    }
}

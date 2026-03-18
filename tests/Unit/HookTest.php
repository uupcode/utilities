<?php

declare(strict_types=1);

namespace UupCode\Utilities\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use UupCode\Utilities\Hook;
use UupCode\Utilities\Tests\TestCase;

final class HookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hook::flush();
    }

    public function testActionRegistersAndRecordsEntry(): void
    {
        $callback = static fn () => null;
        Actions\expectAdded('init')->once();

        Hook::action('init', $callback);

        $this->assertCount(1, Hook::all());
        $this->assertSame('action', Hook::all()[0]['type']);
        $this->assertSame('init', Hook::all()[0]['hook']);
    }

    public function testFilterRegistersAndRecordsEntry(): void
    {
        $callback = static fn ($v) => $v;
        Filters\expectAdded('the_content')->once();

        Hook::filter('the_content', $callback);

        $this->assertCount(1, Hook::filters());
        $this->assertSame('filter', Hook::filters()[0]['type']);
        $this->assertSame('the_content', Hook::filters()[0]['hook']);
    }

    public function testActionsAndFiltersAreSegregated(): void
    {
        Actions\expectAdded('init')->once();
        Filters\expectAdded('the_content')->once();

        Hook::action('init', static fn () => null);
        Hook::filter('the_content', static fn ($v) => $v);

        $this->assertCount(1, Hook::actions());
        $this->assertCount(1, Hook::filters());
        $this->assertCount(2, Hook::all());
    }

    public function testHasReturnsTrueAfterRegistration(): void
    {
        Actions\expectAdded('plugins_loaded')->once();

        Hook::action('plugins_loaded', static fn () => null);

        $this->assertTrue(Hook::has('plugins_loaded'));
        $this->assertFalse(Hook::has('wp_footer'));
    }

    public function testCountReflectsNumberOfRegistrations(): void
    {
        Actions\expectAdded('init')->twice();

        Hook::action('init', static fn () => null);
        Hook::action('init', static fn () => null);

        $this->assertSame(2, Hook::count());
    }

    public function testNamedReturnsOnlyEntriesForGivenHook(): void
    {
        Actions\expectAdded('init')->once();
        Actions\expectAdded('wp_head')->once();

        Hook::action('init', static fn () => null);
        Hook::action('wp_head', static fn () => null);

        $this->assertCount(1, Hook::named('init'));
        $this->assertSame('init', Hook::named('init')[0]['hook']);
    }

    public function testFlushClearsRegistry(): void
    {
        Actions\expectAdded('init')->once();
        Hook::action('init', static fn () => null);
        $this->assertCount(1, Hook::all());

        Hook::flush();
        $this->assertCount(0, Hook::all());
    }

    public function testPriorityAndArgsAreRecorded(): void
    {
        Actions\expectAdded('save_post')->once();

        Hook::action('save_post', static fn () => null, priority: 20, args: 2);

        $entry = Hook::all()[0];
        $this->assertSame(20, $entry['priority']);
        $this->assertSame(2, $entry['args']);
    }
}

<?php

declare(strict_types=1);

namespace UupCode\Utilities\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Action
{
    public function __construct(
        public readonly string $hook,
        public readonly int    $priority = 10,
        public readonly int    $args     = 1,
    ) {
    }
}

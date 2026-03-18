<?php

declare(strict_types=1);

namespace UupCode\Utilities\Database;

/**
 * Wraps a raw SQL fragment that should never be escaped.
 *
 * Use sparingly — only when no typed method covers your case.
 * You are responsible for the safety of the string you pass in.
 *
 * Usage:
 *   DB::table('posts')->select(DB::raw('COUNT(*) AS total'))->value('total');
 *   DB::table('posts')->update(['views' => DB::raw('views + 1')]);
 */
final class Expression
{
    public function __construct(private readonly string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

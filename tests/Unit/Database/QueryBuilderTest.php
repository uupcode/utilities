<?php

declare(strict_types=1);

namespace UupCode\Utilities\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use UupCode\Utilities\Database\Expression;
use UupCode\Utilities\Database\QueryBuilder;

final class QueryBuilderTest extends TestCase
{
    private QueryBuilder $qb;

    protected function setUp(): void
    {
        $this->qb = new QueryBuilder(new \wpdb());
    }

    public function testTableAppliesPrefix(): void
    {
        $sql = $this->qb->table('posts')->toSql();
        $this->assertStringContainsString('`wp_posts`', $sql);
    }

    public function testTableDoesNotDoublePrefix(): void
    {
        $sql = $this->qb->table('wp_posts')->toSql();
        $this->assertStringNotContainsString('wp_wp_posts', $sql);
    }

    public function testSelectSpecificColumns(): void
    {
        $sql = $this->qb->table('posts')->select('ID', 'post_title')->toSql();
        $this->assertStringContainsString('`ID`', $sql);
        $this->assertStringContainsString('`post_title`', $sql);
    }

    public function testSelectRaw(): void
    {
        $sql = $this->qb->table('posts')->selectRaw('COUNT(*) AS total')->toSql();
        $this->assertStringContainsString('COUNT(*) AS total', $sql);
    }

    public function testSelectWithExpression(): void
    {
        $sql = $this->qb->table('posts')
            ->select(new Expression('COUNT(*) AS total'))
            ->toSql();
        $this->assertStringContainsString('COUNT(*) AS total', $sql);
    }

    public function testDistinct(): void
    {
        $sql = $this->qb->table('posts')->distinct()->toSql();
        $this->assertStringContainsString('DISTINCT', $sql);
    }

    public function testWhereProducesWhereClause(): void
    {
        $sql = $this->qb->table('posts')->where('post_status', 'publish')->toSql();
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('`post_status`', $sql);
    }

    public function testWhereWithOperator(): void
    {
        $sql = $this->qb->table('posts')->where('comment_count', '>', 5)->toSql();
        $this->assertStringContainsString('>', $sql);
    }

    public function testWhereClauseHasNoOnePaddingOrLeadingBoolean(): void
    {
        $sql = $this->qb->table('posts')->where('post_status', 'publish')->toSql();
        $this->assertStringNotContainsString('1=1', $sql);
        $this->assertStringNotContainsString('WHERE AND', $sql);
    }

    public function testNestedWhereClosureProducesValidGroup(): void
    {
        $sql = $this->qb->table('posts')
            ->where('post_status', 'publish')
            ->where(function ($q): void {
                $q->whereNull('post_parent')->orWhere('menu_order', '>', 0);
            })
            ->toSql();

        // The group must not start with a dangling boolean: "(AND …)" is invalid SQL.
        $this->assertStringNotContainsString('(AND', $sql);
        // Top-level conditions are still joined with AND, and the group has its OR.
        $this->assertStringContainsString('AND (', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function testWhereNull(): void
    {
        $sql = $this->qb->table('posts')->whereNull('post_parent')->toSql();
        $this->assertStringContainsString('IS NULL', $sql);
    }

    public function testWhereNotNull(): void
    {
        $sql = $this->qb->table('posts')->whereNotNull('post_parent')->toSql();
        $this->assertStringContainsString('IS NOT NULL', $sql);
    }

    public function testWhereInWithEmptyProducesFalsePredicate(): void
    {
        $sql = $this->qb->table('posts')->whereIn('ID', [])->toSql();
        $this->assertStringContainsString('1=0', $sql);
    }

    public function testWhereNotInWithEmptyProducesTruePredicate(): void
    {
        $sql = $this->qb->table('posts')->whereNotIn('ID', [])->toSql();
        $this->assertStringContainsString('1=1', $sql);
    }

    public function testOrderByDesc(): void
    {
        $sql = $this->qb->table('posts')->orderBy('post_date', 'DESC')->toSql();
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('DESC', $sql);
    }

    public function testOrderByDefaultsToAsc(): void
    {
        $sql = $this->qb->table('posts')->orderBy('post_title')->toSql();
        $this->assertStringContainsString('ASC', $sql);
    }

    public function testLimit(): void
    {
        $sql = $this->qb->table('posts')->limit(10)->toSql();
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testOffset(): void
    {
        $sql = $this->qb->table('posts')->offset(20)->toSql();
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function testForPage(): void
    {
        $sql = $this->qb->table('posts')->forPage(3, 15)->toSql();
        $this->assertStringContainsString('LIMIT 15', $sql);
        $this->assertStringContainsString('OFFSET 30', $sql);
    }

    public function testGroupBy(): void
    {
        $sql = $this->qb->table('posts')->groupBy('post_status')->toSql();
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('`post_status`', $sql);
    }

    public function testInvalidOperatorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->qb->table('posts')->where('ID', 'DROP TABLE', 1)->toSql();
    }

    public function testInvalidIdentifierThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->qb->table('posts')->where('ID; DROP TABLE posts--', 'foo')->toSql();
    }
}

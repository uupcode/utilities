<?php

declare(strict_types=1);

namespace UupCode\Utilities\Database;

/**
 * Static facade — the single entry point for all queries.
 *
 * Mirrors the Laravel DB facade so the API feels familiar.
 *
 * Usage examples:
 *
 *   // Basic query
 *   DB::table('posts')
 *       ->where('post_status', 'publish')
 *       ->orderBy('post_date', 'DESC')
 *       ->limit(10)
 *       ->get();
 *
 *   // Aggregates
 *   $total = DB::table('posts')->where('post_type', 'product')->count();
 *   $avg   = DB::table('postmeta')->where('meta_key', '_price')->avg('meta_value');
 *
 *   // Write
 *   $id = DB::table('options')->insert(['option_name' => 'foo', 'option_value' => 'bar']);
 *
 *   DB::table('posts')
 *       ->where('ID', 5)
 *       ->update(['post_status' => 'draft']);
 *
 *   // Increment a counter without a race condition
 *   DB::table('posts')
 *       ->where('ID', 5)
 *       ->update(['views' => DB::raw('views + 1')]);
 *
 *   // Batch insert
 *   DB::table('postmeta')->insertBatch([
 *       ['post_id' => 1, 'meta_key' => '_color', 'meta_value' => 'blue'],
 *       ['post_id' => 2, 'meta_key' => '_color', 'meta_value' => 'red'],
 *   ]);
 *
 *   // Raw expression in SELECT
 *   DB::table('posts')
 *       ->selectRaw('post_status, COUNT(*) AS total')
 *       ->groupBy('post_status')
 *       ->get();
 *
 *   // Subquery-style exists check
 *   $exists = DB::table('users')->where('user_email', $email)->exists();
 *
 *   // Transaction
 *   DB::transaction(function () {
 *       DB::table('options')->where('option_name', 'credits')->update(['option_value' => DB::raw('option_value - 1')]);
 *       DB::table('orders')->insert(['user_id' => 1, 'status' => 'paid']);
 *   });
 *
 *   // Raw statement (DDL, stored procs, etc.)
 *   DB::statement('ALTER TABLE `wp_my_table` ADD INDEX (`user_id`)');
 *
 *   // Direct parameterised SELECT (when the fluent builder is overkill)
 *   $rows = DB::select('SELECT * FROM wp_posts WHERE ID = %d AND post_status = %s', [5, 'publish']);
 */
final class DB
{
    // ─── Query builder entry ──────────────────────────────────────────────────

    /**
     * Start a fluent query against the given table.
     * The WordPress table prefix is applied automatically.
     *
     *   DB::table('posts')     // → wp_posts
     *   DB::table('users')     // → wp_users
     */
    public static function table(string $table): QueryBuilder
    {
        return (new QueryBuilder(self::wpdb()))->table($table);
    }

    /**
     * Create a raw SQL expression that will not be escaped.
     *
     *   DB::table('posts')->select(DB::raw('COUNT(*) AS total'))
     *   DB::table('posts')->update(['views' => DB::raw('views + 1')])
     */
    public static function raw(string $expression): Expression
    {
        return new Expression($expression);
    }

    // ─── Transactions ─────────────────────────────────────────────────────────

    /**
     * Run a callable inside a database transaction.
     * Commits on success, rolls back on any Throwable.
     *
     *   DB::transaction(function () {
     *       DB::table('accounts')->where('id', 1)->update(['balance' => DB::raw('balance - 100')]);
     *       DB::table('accounts')->where('id', 2)->update(['balance' => DB::raw('balance + 100')]);
     *   });
     */
    public static function transaction(callable $callback): mixed
    {
        $db = self::wpdb();
        $db->query('START TRANSACTION');

        try {
            $result = $callback();
            $db->query('COMMIT');
            return $result;
        } catch (\Throwable $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }

    public static function beginTransaction(): void
    {
        self::wpdb()->query('START TRANSACTION');
    }

    public static function commit(): void
    {
        self::wpdb()->query('COMMIT');
    }

    public static function rollback(): void
    {
        self::wpdb()->query('ROLLBACK');
    }

    // ─── Raw query helpers ────────────────────────────────────────────────────

    /**
     * Run any SQL statement that doesn't return rows (DDL, SET, etc.).
     *
     *   DB::statement('CREATE INDEX ...');
     *   DB::statement('SET SESSION group_concat_max_len = %d', [100000]);
     */
    /** @param array<mixed> $bindings */
    public static function statement(string $sql, array $bindings = []): bool
    {
        $db      = self::wpdb();
        $prepared = empty($bindings) ? $sql : $db->prepare($sql, ...$bindings);
        return $db->query($prepared) !== false;
    }

    /**
     * Run a raw SELECT and return all rows as objects.
     *
     *   DB::select('SELECT * FROM wp_posts WHERE ID IN (%d, %d)', [1, 2]);
     */
    /**
     * @param  array<mixed>  $bindings
     * @return list<object>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        $db       = self::wpdb();
        $prepared = empty($bindings) ? $sql : $db->prepare($sql, ...$bindings);
        return $db->get_results($prepared) ?? [];
    }

    /**
     * Run a raw SELECT and return only the first row, or null.
     *
     *   DB::selectOne('SELECT option_value FROM wp_options WHERE option_name = %s', ['siteurl']);
     */
    /** @param array<mixed> $bindings */
    public static function selectOne(string $sql, array $bindings = []): ?object
    {
        return self::select($sql, $bindings)[0] ?? null;
    }

    /**
     * Return a single scalar value from a raw query.
     *
     *   $count = DB::scalar('SELECT COUNT(*) FROM wp_posts WHERE post_status = %s', ['publish']);
     */
    /** @param array<mixed> $bindings */
    public static function scalar(string $sql, array $bindings = []): mixed
    {
        $db       = self::wpdb();
        $prepared = empty($bindings) ? $sql : $db->prepare($sql, ...$bindings);
        return $db->get_var($prepared);
    }

    // ─── Query logging ────────────────────────────────────────────────────────

    /**
     * Enable wpdb's built-in query log.
     * Retrieve entries with DB::getQueryLog().
     */
    public static function enableQueryLog(): void
    {
        self::wpdb()->show_errors();
        if (! defined('SAVEQUERIES')) {
            // SAVEQUERIES must be defined before wpdb is instantiated to have
            // full effect, but toggling the property works for subsequent queries.
            self::wpdb()->save_queries = 1; // @phpstan-ignore-line
        }
    }

    /**
     * Return all queries logged by wpdb since the log was enabled.
     *
     * Each entry is: [ $sql, $duration_seconds, $calling_function ]
     *
     * @return list<array{0:string,1:float,2:string}>
     */
    public static function getQueryLog(): array
    {
        // @phpstan-ignore-next-line
        return self::wpdb()->queries ?? [];
    }

    public static function flushQueryLog(): void
    {
        self::wpdb()->queries = [];
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private static function wpdb(): \wpdb
    {
        global $wpdb;
        if (! ($wpdb instanceof \wpdb)) {
            throw new \RuntimeException('$wpdb is not available. Ensure WordPress is fully loaded.');
        }
        return $wpdb;
    }
}

<?php

declare(strict_types=1);

namespace UupCode\Utilities\Database;

/**
 * Fluent SQL query builder wrapping $wpdb.
 *
 * ─── Safety guarantees ───────────────────────────────────────────────────────
 *
 * Every user-supplied VALUE goes through $wpdb->prepare() — never string
 * concatenation.  Column and table identifiers are validated against a strict
 * alphanumeric pattern and backtick-quoted before use.
 *
 * The only intentional escape hatch is Expression (DB::raw()), whose safety
 * is the caller's responsibility, exactly like Laravel's DB::raw().
 *
 * ─── Design notes ─────────────────────────────────────────────────────────
 *
 * Each WHERE / HAVING condition is compiled to a safe SQL string immediately
 * when the method is called (not lazily at toSql() time).  This means:
 *  - No "pending bindings" array to accidentally misalign.
 *  - toSql() / get() just concatenates already-safe fragments.
 *
 * The builder is mutable (returns $this) for lightweight chaining.
 * Clone the builder yourself if you need to fork a base query.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Quick reference:
 *
 *   DB::table('posts')                             // auto-prefixed → wp_posts
 *      ->select('ID', 'post_title')
 *      ->where('post_status', 'publish')
 *      ->where('post_author', '!=', 3)
 *      ->whereIn('ID', [1, 2, 3])
 *      ->whereBetween('comment_count', 1, 100)
 *      ->whereLike('post_title', 'hello')
 *      ->whereNull('post_parent')
 *      ->orWhere(function ($q) {
 *          $q->where('post_type', 'page')->where('menu_order', '>', 0);
 *      })
 *      ->join('users', 'posts.post_author', '=', 'users.ID')
 *      ->leftJoin('postmeta', 'posts.ID', '=', 'postmeta.post_id')
 *      ->orderBy('post_date', 'DESC')
 *      ->limit(10)
 *      ->offset(20)
 *      ->get();
 */
class QueryBuilder
{
    // ─── Allowed operators (whitelist prevents injection via operator param) ──

    private const OPERATORS = [
        '=', '!=', '<>', '<', '>', '<=', '>=',
        'LIKE', 'NOT LIKE', 'REGEXP', 'NOT REGEXP',
    ];

    // ─── State ────────────────────────────────────────────────────────────────

    private string  $from      = '';
    /** @var list<string> */
    private array   $columns   = ['*'];
    private bool    $isDistinct = false;
    /** @var list<string> */
    private array   $joins     = [];
    /** @var list<array{sql:string,boolean:string}> */
    private array   $wheres    = [];
    /** @var list<string> */
    private array   $groups    = [];
    /** @var list<array{sql:string,boolean:string}> */
    private array   $havings   = [];
    /** @var list<string> */
    private array   $orders    = [];
    private ?int    $limitVal  = null;
    private ?int    $offsetVal = null;

    public function __construct(private readonly \wpdb $db)
    {
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TABLE / FROM
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Set the table.  The WordPress table prefix is applied automatically
     * unless the name already starts with it.
     *
     *   DB::table('posts')    // → wp_posts
     *   DB::table('wp_posts') // → wp_posts  (no double-prefix)
     */
    public function table(string $table): static
    {
        $this->from = $this->prefixTable($table);
        return $this;
    }

    /** Alias for table(). */
    public function from(string $table): static
    {
        return $this->table($table);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SELECT
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Set the SELECT columns.
     *
     *   ->select('ID', 'post_title')
     *   ->select(DB::raw('COUNT(*) AS total'), 'post_status')
     */
    public function select(string|Expression ...$columns): static
    {
        $this->columns = array_map($this->wrapColumnOrRaw(...), $columns);
        return $this;
    }

    /** Append columns to an existing SELECT. */
    public function addSelect(string|Expression ...$columns): static
    {
        $new = array_map($this->wrapColumnOrRaw(...), $columns);
        $this->columns = $this->columns === ['*'] ? $new : array_merge($this->columns, $new);
        return $this;
    }

    /** Raw SELECT expression (not escaped). */
    public function selectRaw(string $sql): static
    {
        $this->columns = [ $sql ];
        return $this;
    }

    public function distinct(): static
    {
        $this->isDistinct = true;
        return $this;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // JOIN
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Add a JOIN clause.
     *
     *   ->join('users', 'posts.post_author', '=', 'users.ID')
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $this->assertOperator($operator);
        $t = $this->prefixTable($table);
        $this->joins[] = sprintf(
            '%s JOIN `%s` ON %s %s %s',
            strtoupper($type),
            $t,
            $this->wrapCol($first),
            $operator,
            $this->wrapCol($second)
        );
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /** Raw JOIN string — you are responsible for safety. */
    public function joinRaw(string $sql): static
    {
        $this->joins[] = $sql;
        return $this;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // WHERE
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Add a WHERE condition.
     *
     *   ->where('post_status', 'publish')          // col = val
     *   ->where('comment_count', '>', 5)           // col op val
     *   ->where(['post_type' => 'post', 'status' => 'publish'])  // array shorthand
     *   ->where(function ($q) { $q->where(...)->orWhere(...); }) // nested group
     */
    public function where(string|array|\Closure $column, mixed $operatorOrValue = null, mixed $value = null, string $boolean = 'AND'): static
    {
        // Array shorthand: ->where(['col' => 'val', ...])
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->where($col, $val, null, $boolean);
            }
            return $this;
        }

        // Closure: nested group with its own AND/OR logic
        if ($column instanceof \Closure) {
            $sub         = new self($this->db);
            $sub->from   = $this->from;
            $column($sub);
            $nested = $sub->compileWheres();
            if ($nested !== '') {
                $this->wheres[] = [ 'sql' => "({$nested})", 'boolean' => $boolean ];
            }
            return $this;
        }

        [ $operator, $resolvedValue ] = $this->normalizeOperatorAndValue($operatorOrValue, $value);

        // Redirect null values to IS NULL / IS NOT NULL
        if ($resolvedValue === null) {
            return $this->whereNull($column, $boolean, in_array($operator, [ '!=', '<>' ], true));
        }

        $col    = $this->wrapCol($column);
        $format = $this->formatFor($resolvedValue);

        $this->wheres[] = [
            'sql'     => $this->db->prepare("{$col} {$operator} {$format}", $resolvedValue),
            'boolean' => $boolean,
        ];

        return $this;
    }

    /** OR WHERE. */
    public function orWhere(string|array|\Closure $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        return $this->where($column, $operatorOrValue, $value, 'OR');
    }

    /** Raw WHERE condition — bindings are passed to $wpdb->prepare(). */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'sql'     => empty($bindings) ? $sql : $this->db->prepare($sql, ...$bindings),
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        return $this->whereRaw($sql, $bindings, 'OR');
    }

    /**
     * WHERE col IN (…).
     *
     *   ->whereIn('ID', [1, 2, 3])
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): static
    {
        $operator = $not ? 'NOT IN' : 'IN';

        if (empty($values)) {
            // Empty IN is always false; empty NOT IN is always true.
            $this->wheres[] = [ 'sql' => $not ? '1=1' : '1=0', 'boolean' => $boolean ];
            return $this;
        }

        $col          = $this->wrapCol($column);
        $placeholders = implode(', ', array_map($this->formatFor(...), $values));

        $this->wheres[] = [
            'sql'     => $this->db->prepare("{$col} {$operator} ({$placeholders})", ...$values),
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'OR');
    }

    public function orWhereNotIn(string $column, array $values): static
    {
        return $this->whereNotIn($column, $values, 'OR');
    }

    /** WHERE col IS NULL / IS NOT NULL. */
    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): static
    {
        $this->wheres[] = [
            'sql'     => $this->wrapCol($column) . ($not ? ' IS NOT NULL' : ' IS NULL'),
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'OR');
    }

    public function orWhereNotNull(string $column): static
    {
        return $this->whereNotNull($column, 'OR');
    }

    /** WHERE col BETWEEN min AND max. */
    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND', bool $not = false): static
    {
        $col      = $this->wrapCol($column);
        $operator = $not ? 'NOT BETWEEN' : 'BETWEEN';
        $fMin     = $this->formatFor($min);
        $fMax     = $this->formatFor($max);

        $this->wheres[] = [
            'sql'     => $this->db->prepare("{$col} {$operator} {$fMin} AND {$fMax}", $min, $max),
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND'): static
    {
        return $this->whereBetween($column, $min, $max, $boolean, true);
    }

    /**
     * Compare two columns — neither side is bound as a value.
     *
     *   ->whereColumn('posts.post_author', '=', 'users.ID')
     */
    public function whereColumn(string $first, string $operator, string $second, string $boolean = 'AND'): static
    {
        $this->assertOperator($operator);
        $this->wheres[] = [
            'sql'     => "{$this->wrapCol($first)} {$operator} {$this->wrapCol($second)}",
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function orWhereColumn(string $first, string $operator, string $second): static
    {
        return $this->whereColumn($first, $operator, $second, 'OR');
    }

    /**
     * WHERE col LIKE '%value%' — value is safely escaped with $wpdb->esc_like().
     *
     *   ->whereLike('post_title', 'hello world')
     */
    public function whereLike(string $column, string $value, string $boolean = 'AND'): static
    {
        $like = '%' . $this->db->esc_like($value) . '%';
        $this->wheres[] = [
            'sql'     => $this->db->prepare($this->wrapCol($column) . ' LIKE %s', $like),
            'boolean' => $boolean,
        ];
        return $this;
    }

    public function orWhereLike(string $column, string $value): static
    {
        return $this->whereLike($column, $value, 'OR');
    }

    public function whereNotLike(string $column, string $value, string $boolean = 'AND'): static
    {
        $like = '%' . $this->db->esc_like($value) . '%';
        $this->wheres[] = [
            'sql'     => $this->db->prepare($this->wrapCol($column) . ' NOT LIKE %s', $like),
            'boolean' => $boolean,
        ];
        return $this;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GROUP BY / HAVING
    // ═══════════════════════════════════════════════════════════════════════════

    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $col) {
            $this->groups[] = $this->wrapCol($col);
        }
        return $this;
    }

    public function groupByRaw(string $sql): static
    {
        $this->groups[] = $sql;
        return $this;
    }

    /** HAVING col op val. */
    public function having(string $column, string $operator, mixed $value, string $boolean = 'AND'): static
    {
        $this->assertOperator($operator);
        $col = $this->wrapCol($column);
        $fmt = $this->formatFor($value);

        $this->havings[] = [
            'sql'     => $this->db->prepare("{$col} {$operator} {$fmt}", $value),
            'boolean' => $boolean,
        ];
        return $this;
    }

    /** Raw HAVING — bindings go through prepare(). */
    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'AND'): static
    {
        $this->havings[] = [
            'sql'     => empty($bindings) ? $sql : $this->db->prepare($sql, ...$bindings),
            'boolean' => $boolean,
        ];
        return $this;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ORDER BY
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     *   ->orderBy('post_date', 'DESC')
     *   ->orderBy('post_title')           // default ASC
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $dir            = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $this->wrapCol($column) . ' ' . $dir;
        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'DESC');
    }

    /** Raw ORDER BY — not escaped. */
    public function orderByRaw(string $expression): static
    {
        $this->orders[] = $expression;
        return $this;
    }

    /** Shorthand for orderBy($col, 'DESC'). Defaults to post_date for WP. */
    public function latest(string $column = 'post_date'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'post_date'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    /** Clear all ORDER BY clauses. */
    public function reorder(): static
    {
        $this->orders = [];
        return $this;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LIMIT / OFFSET / PAGINATION
    // ═══════════════════════════════════════════════════════════════════════════

    public function limit(int $value): static
    {
        $this->limitVal = max(0, $value);
        return $this;
    }

    /** Alias for limit(). */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    public function offset(int $value): static
    {
        $this->offsetVal = max(0, $value);
        return $this;
    }

    /** Alias for offset(). */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    /**
     * Set limit and offset for a given page number.
     *
     *   ->forPage(2, 15)   // LIMIT 15 OFFSET 15
     */
    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset((max(1, $page) - 1) * $perPage)->limit($perPage);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // READ EXECUTION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Execute the query and return all rows as an array of objects.
     *
     * @return list<object>
     */
    public function get(): array
    {
        return $this->db->get_results($this->toSql()) ?? [];
    }

    /**
     * Return the first row, or null if no results.
     */
    public function first(): ?object
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    /**
     * Find a single row by its primary key.
     *
     *   ->find(5)           // WHERE ID = 5
     *   ->find(5, 'post_id')
     */
    public function find(int|string $id, string $key = 'ID'): ?object
    {
        return $this->where($key, $id)->first();
    }

    /**
     * Return the value of a single column from the first row.
     *
     *   DB::table('options')->where('option_name', 'siteurl')->value('option_value');
     */
    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        return $row?->{$column} ?? null;
    }

    /**
     * Return a flat array of values for a single column.
     * Pass $key to get an associative array keyed by another column.
     *
     *   ->pluck('post_title')                      // ['Hello', 'World']
     *   ->pluck('post_title', 'ID')                // [5 => 'Hello', 6 => 'World']
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $rows = $key ? $this->select($column, $key)->get() : $this->select($column)->get();

        if ($key !== null) {
            $result = [];
            foreach ($rows as $row) {
                $result[ $row->{$key} ?? '' ] = $row->{$column} ?? null;
            }
            return $result;
        }

        return array_map(fn ($row) => $row->{$column} ?? null, $rows);
    }

    /**
     * Process results in chunks to avoid loading thousands of rows into memory.
     *
     *   DB::table('posts')->chunk(100, function ($posts) {
     *       foreach ($posts as $post) { ... }
     *   });
     */
    public function chunk(int $size, callable $callback): bool
    {
        $page = 1;
        do {
            $rows = (clone $this)->forPage($page++, $size)->get();
            if (empty($rows)) {
                break;
            }
            if ($callback($rows) === false) {
                return false;
            }
        } while (count($rows) === $size);

        return true;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // AGGREGATES
    // ═══════════════════════════════════════════════════════════════════════════

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('COUNT', $column);
    }

    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    public function aggregate(string $function, string $column = '*'): mixed
    {
        $col = $column === '*' ? '*' : $this->wrapCol($column);
        $sql = $this->compileSelectStatement(strtoupper($function) . "({$col}) AS aggregate");
        return $this->db->get_var($sql);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // WRITE EXECUTION
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Insert a single row.  Returns the new row's auto-increment ID or false.
     *
     * Delegates to $wpdb->insert() which handles all escaping.
     *
     *   DB::table('posts')->insert(['post_title' => 'Hello', 'post_status' => 'publish']);
     */
    public function insert(array $data): int|false
    {
        $result = $this->db->insert($this->from, $data);
        return $result !== false ? $this->db->insert_id : false;
    }

    /**
     * Insert multiple rows in a single query.
     *
     * All values are bound via $wpdb->prepare() in one call —
     * no per-row execute round-trips.
     *
     *   DB::table('postmeta')->insertBatch([
     *       ['post_id' => 1, 'meta_key' => '_featured', 'meta_value' => '1'],
     *       ['post_id' => 2, 'meta_key' => '_featured', 'meta_value' => '0'],
     *   ]);
     */
    public function insertBatch(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $columns  = array_keys(reset($rows));
        $colsSql  = implode(', ', array_map(fn ($c) => "`{$c}`", $columns));

        $allValues        = [];
        $valGroupTemplates = [];

        foreach ($rows as $row) {
            $rowFormats = [];
            foreach ($columns as $col) {
                $val = $row[ $col ] ?? null;
                if ($val instanceof Expression) {
                    $rowFormats[] = $val->getValue();
                } elseif ($val === null) {
                    $rowFormats[] = 'NULL';
                } else {
                    $rowFormats[]  = $this->formatFor($val);
                    $allValues[]   = $val;
                }
            }
            $valGroupTemplates[] = '(' . implode(', ', $rowFormats) . ')';
        }

        $sql = "INSERT INTO `{$this->from}` ({$colsSql}) VALUES " . implode(', ', $valGroupTemplates);

        if (! empty($allValues)) {
            $sql = $this->db->prepare($sql, ...$allValues);
        }

        return (int) $this->db->query($sql);
    }

    /**
     * Update rows that match the current WHERE clauses.
     *
     *   DB::table('posts')->where('ID', 5)->update(['post_status' => 'draft']);
     *   DB::table('posts')->where('ID', 5)->update(['views' => DB::raw('views + 1')]);
     */
    public function update(array $data): int
    {
        $setValues  = [];
        $setParts   = [];

        foreach ($data as $column => $value) {
            $col = '`' . $column . '`';
            if ($value instanceof Expression) {
                $setParts[] = "{$col} = {$value->getValue()}";
            } elseif ($value === null) {
                $setParts[] = "{$col} = NULL";
            } else {
                // Prepare each SET value individually — avoids any conflict
                // with the already-compiled WHERE fragments appended below.
                $setParts[] = $col . ' = ' . $this->db->prepare($this->formatFor($value), $value);
            }
        }

        if (empty($setParts)) {
            return 0;
        }

        $sql = "UPDATE `{$this->from}` SET " . implode(', ', $setParts);

        $where = $this->compileWheres();
        if ($where !== '') {
            $sql .= ' WHERE 1=1 ' . $where;
        }

        return (int) $this->db->query($sql);
    }

    /**
     * Update matching rows or insert if none found.
     *
     *   DB::table('options')->updateOrInsert(
     *       ['option_name' => 'my_key'],
     *       ['option_value' => 'new_value']
     *   );
     */
    public function updateOrInsert(array $attributes, array $values): bool
    {
        $query = new self($this->db);
        $query->table($this->from);
        foreach ($attributes as $col => $val) {
            $query->where($col, $val);
        }

        if ($query->exists()) {
            return (bool) $query->update($values);
        }

        return (bool) $this->insert(array_merge($attributes, $values));
    }

    /**
     * Delete rows matching the current WHERE clauses.
     *
     *   DB::table('posts')->where('post_status', 'trash')->delete();
     */
    public function delete(): int
    {
        $sql   = "DELETE FROM `{$this->from}`";
        $where = $this->compileWheres();

        if ($where !== '') {
            $sql .= ' WHERE 1=1 ' . $where;
        }

        return (int) $this->db->query($sql);
    }

    public function truncate(): bool
    {
        return (bool) $this->db->query("TRUNCATE TABLE `{$this->from}`");
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DEBUG
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Return the fully compiled SELECT SQL (values already interpolated safely).
     * Useful for logging, testing, or EXPLAIN queries.
     */
    public function toSql(): string
    {
        return $this->compileSelectStatement(implode(', ', $this->columns));
    }

    /** Dump the compiled SQL and continue. */
    public function dump(): static
    {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump
        var_dump($this->toSql());
        return $this;
    }

    /** Dump the compiled SQL and halt. */
    public function dd(): never
    {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump
        var_dump($this->toSql());
        exit(1);
    }

    /** Return a fresh builder sharing this one's DB connection. */
    public function newQuery(): static
    {
        return new static($this->db);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SQL COMPILATION  (private)
    // ═══════════════════════════════════════════════════════════════════════════

    private function compileSelectStatement(string $select): string
    {
        $distinct = $this->isDistinct ? 'DISTINCT ' : '';
        $sql      = "SELECT {$distinct}{$select} FROM `{$this->from}`";

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $where = $this->compileWheres();
        if ($where !== '') {
            $sql .= ' WHERE 1=1 ' . $where;
        }

        if ($this->groups) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        $having = $this->compileHavings();
        if ($having !== '') {
            $sql .= ' HAVING ' . $having;
        }

        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }

        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }

        return $sql;
    }

    /**
     * Compile all WHERE conditions into a single SQL string.
     * Each entry was already run through prepare() when added.
     * We just join them with their AND/OR boolean prefix.
     */
    private function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        $parts = [];
        foreach ($this->wheres as $w) {
            $parts[] = "{$w['boolean']} {$w['sql']}";
        }
        return implode(' ', $parts);
    }

    private function compileHavings(): string
    {
        if (empty($this->havings)) {
            return '';
        }
        $parts = [];
        foreach ($this->havings as $i => $h) {
            $parts[] = ($i === 0 ? '' : $h['boolean'] . ' ') . $h['sql'];
        }
        return implode(' ', $parts);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS  (private)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Returns backtick-quoted `table`.`column` or just `column`.
     * Validates the identifier to prevent injection via column names.
     */
    private function wrapCol(string $column): string
    {
        if ($column === '*') {
            return '*';
        }

        // Allow table.column notation.
        if (str_contains($column, '.')) {
            [ $table, $col ] = explode('.', $column, 2);
            $this->assertIdentifier($table);
            if ($col !== '*') {
                $this->assertIdentifier($col);
            }
            return "`{$table}`.`{$col}`";
        }

        $this->assertIdentifier($column);
        return "`{$column}`";
    }

    /** Accepts both a plain string and an Expression. */
    private function wrapColumnOrRaw(string|Expression $column): string
    {
        return $column instanceof Expression ? $column->getValue() : $this->wrapCol($column);
    }

    private function assertIdentifier(string $name): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: '{$name}'");
        }
    }

    private function assertOperator(string $operator): void
    {
        if (! in_array(strtoupper($operator), self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Disallowed operator: '{$operator}'");
        }
    }

    /**
     * Resolve the two-or-three-argument where() pattern.
     *
     *   where('col', 'val')       → ['=', 'val']
     *   where('col', '>', 5)      → ['>', 5]
     *
     * @return array{0:string, 1:mixed}
     */
    private function normalizeOperatorAndValue(mixed $operatorOrValue, mixed $value): array
    {
        // Two-arg form: second arg is the value, operator defaults to '='.
        if ($value === null) {
            return [ '=', $operatorOrValue ];
        }

        // Three-arg form: first is operator, second is value.
        $op = strtoupper(trim((string) $operatorOrValue));
        $this->assertOperator($op);
        return [ $op, $value ];
    }

    /**
     * Returns the correct $wpdb->prepare() placeholder for a given value.
     * Strings use %s; integers use %d; floats use %f.
     */
    private function formatFor(mixed $value): string
    {
        return match (true) {
            is_int($value)   => '%d',
            is_float($value) => '%f',
            default            => '%s',
        };
    }

    /** Apply the WordPress table prefix unless it's already present. */
    private function prefixTable(string $table): string
    {
        if (str_starts_with($table, $this->db->prefix)) {
            return $table;
        }
        return $this->db->prefix . $table;
    }
}

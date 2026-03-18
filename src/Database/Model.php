<?php

declare(strict_types=1);

namespace UupCode\Utilities\Database;

/**
 * Base model with automatic column casting and query builder access.
 *
 * Usage:
 *
 *   class OrderModel extends Model
 *   {
 *       protected static string $table = 'myplugin_orders';
 *
 *       protected static array $casts = [
 *           'meta'     => 'array',   // JSON → PHP array
 *           'settings' => 'object',  // JSON → stdClass
 *           'amount'   => 'float',
 *           'is_paid'  => 'bool',
 *       ];
 *   }
 *
 *   OrderModel::all();
 *   OrderModel::find(5);
 *
 *   // Custom query with automatic casting:
 *   OrderModel::hydrate(
 *       OrderModel::query()->where('status', 'paid')->orderBy('created_at', 'DESC')->get()
 *   );
 */
abstract class Model
{
    protected static string $table = '';

    /**
     * Column → cast type map.
     *
     * Supported types: 'array', 'object', 'int', 'float', 'bool', 'string'
     *
     * @var array<string, string>
     */
    protected static array $casts = [];

    // ─── Table ────────────────────────────────────────────────────────────────

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . static::$table;
    }

    // ─── Query builder ────────────────────────────────────────────────────────

    /**
     * Return a QueryBuilder scoped to this model's table.
     * Use this in subclass methods to build custom queries,
     * then pass results through hydrate() to apply casting.
     *
     *   public static function published(): array
     *   {
     *       return static::hydrate(
     *           static::query()->where('status', 'published')->get()
     *       );
     *   }
     */
    protected static function query(): QueryBuilder
    {
        return DB::table(static::table());
    }

    // ─── Built-in query methods ───────────────────────────────────────────────

    /** @return list<object> */
    public static function all(): array
    {
        return static::hydrate(static::query()->get());
    }

    public static function find(int $id, string $key = 'id'): ?object
    {
        $row = static::query()->where($key, $id)->first();
        return $row ? static::castRow($row) : null;
    }

    // ─── Casting ──────────────────────────────────────────────────────────────

    /**
     * Apply casts to an array of raw DB rows.
     *
     * @param  list<object> $rows
     * @return list<object>
     */
    public static function hydrate(array $rows): array
    {
        return array_map(fn ($row) => static::castRow($row), $rows);
    }

    protected static function castRow(object $row): object
    {
        foreach (static::$casts as $column => $type) {
            if (isset($row->$column)) {
                $row->$column = static::castValue($row->$column, $type);
            }
        }
        return $row;
    }

    protected static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'array'  => is_array($value) ? $value : (array) json_decode((string) $value, true),
            'object' => is_object($value) ? $value : json_decode((string) $value),
            'int'    => (int) $value,
            'float'  => (float) $value,
            'bool'   => (bool) $value,
            'string' => (string) $value,
            default  => $value,
        };
    }
}

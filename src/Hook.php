<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Static hook registry — wraps add_action / add_filter and keeps a record
 * of every registration made through this class.
 *
 * Usage:
 *
 *   Hook::action('wp_head', [MyPlugin::class, 'outputStyles']);
 *   Hook::action('save_post', [MyPlugin::class, 'onSave'], priority: 20, args: 2);
 *
 *   Hook::filter('the_content', [MyPlugin::class, 'processContent']);
 *   Hook::filter('body_class', fn($classes) => [...$classes, 'my-class']);
 *
 *   // Removal (removes from WP and from the registry)
 *   Hook::remove('wp_head', [MyPlugin::class, 'outputStyles']);
 *   Hook::removeFilter('the_content', [MyPlugin::class, 'processContent']);
 *
 *   // Inspection
 *   Hook::all();                  // every registered entry
 *   Hook::actions();              // only actions
 *   Hook::filters();              // only filters
 *   Hook::for('wp_head');         // all entries for one hook name
 *   Hook::has('wp_head');         // bool
 *   Hook::count();                // total count
 *   Hook::flush();                // clear registry (useful in tests)
 */
final class Hook
{
    /**
     * Every registered hook entry.
     *
     * Shape: {
     *   type:     'action'|'filter',
     *   hook:     string,
     *   callback: callable,
     *   priority: int,
     *   args:     int,
     *   file:     string,
     *   line:     int,
     * }
     *
     * @var list<array{type:string,hook:string,callback:callable,priority:int,args:int,file:string,line:int}>
     */
    private static array $registry = [];

    // ─── Registration ─────────────────────────────────────────────────────────

    /**
     * Register an action hook and record it.
     *
     * @param callable $callback
     */
    public static function action(
        string   $hook,
        callable $callback,
        int      $priority = 10,
        int      $args     = 1,
    ): void {
        add_action($hook, $callback, $priority, $args);
        self::record('action', $hook, $callback, $priority, $args);
    }

    /**
     * Register a filter hook and record it.
     *
     * @param callable $callback
     */
    public static function filter(
        string   $hook,
        callable $callback,
        int      $priority = 10,
        int      $args     = 1,
    ): void {
        add_filter($hook, $callback, $priority, $args);
        self::record('filter', $hook, $callback, $priority, $args);
    }

    // ─── Removal ──────────────────────────────────────────────────────────────

    /**
     * Remove an action from WordPress and from the registry.
     *
     * @param callable $callback
     */
    public static function remove(
        string   $hook,
        callable $callback,
        int      $priority = 10,
    ): void {
        remove_action($hook, $callback, $priority);
        self::deregister('action', $hook, $callback, $priority);
    }

    /**
     * Remove a filter from WordPress and from the registry.
     *
     * @param callable $callback
     */
    public static function removeFilter(
        string   $hook,
        callable $callback,
        int      $priority = 10,
    ): void {
        remove_filter($hook, $callback, $priority);
        self::deregister('filter', $hook, $callback, $priority);
    }

    // ─── Retrieval ────────────────────────────────────────────────────────────

    /**
     * Return all registered entries.
     *
     * @return list<array{type:string,hook:string,callback:callable,priority:int,args:int,file:string,line:int}>
     */
    public static function all(): array
    {
        return self::$registry;
    }

    /**
     * Return only action entries.
     *
     * @return list<array{type:string,hook:string,callback:callable,priority:int,args:int,file:string,line:int}>
     */
    public static function actions(): array
    {
        return array_values(
            array_filter(self::$registry, fn ($e) => $e['type'] === 'action')
        );
    }

    /**
     * Return only filter entries.
     *
     * @return list<array{type:string,hook:string,callback:callable,priority:int,args:int,file:string,line:int}>
     */
    public static function filters(): array
    {
        return array_values(
            array_filter(self::$registry, fn ($e) => $e['type'] === 'filter')
        );
    }

    /**
     * Return all entries registered under a specific hook name.
     *
     * Named `named()` rather than `for()` because `for` is a reserved PHP keyword.
     *
     * @return list<array{type:string,hook:string,callback:callable,priority:int,args:int,file:string,line:int}>
     */
    public static function named(string $hook): array
    {
        return array_values(
            array_filter(self::$registry, fn ($e) => $e['hook'] === $hook)
        );
    }

    /**
     * Check whether any callback has been registered under the given hook name.
     */
    public static function has(string $hook): bool
    {
        foreach (self::$registry as $entry) {
            if ($entry['hook'] === $hook) {
                return true;
            }
        }
        return false;
    }

    /**
     * Total number of registered entries.
     */
    public static function count(): int
    {
        return count(self::$registry);
    }

    /**
     * Clear the registry.
     * Does NOT remove the hooks from WordPress — only clears the local record.
     * Primarily useful in unit tests.
     */
    public static function flush(): void
    {
        self::$registry = [];
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /**
     * @param callable $callback
     */
    private static function record(
        string   $type,
        string   $hook,
        callable $callback,
        int      $priority,
        int      $args,
    ): void {
        // Walk up the call stack past Hook::action/filter → the caller's frame.
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $frame = $trace[2] ?? $trace[1] ?? [];

        self::$registry[] = [
            'type'     => $type,
            'hook'     => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args'     => $args,
            'file'     => $frame['file'] ?? 'unknown',
            'line'     => $frame['line'] ?? 0,
        ];
    }

    /**
     * Remove matching entries from the local registry.
     *
     * @param callable $callback
     */
    private static function deregister(
        string   $type,
        string   $hook,
        callable $callback,
        int      $priority,
    ): void {
        self::$registry = array_values(
            array_filter(
                self::$registry,
                static function (array $entry) use ($type, $hook, $callback, $priority): bool {
                    return ! (
                        $entry['type']     === $type     &&
                        $entry['hook']     === $hook     &&
                        $entry['priority'] === $priority &&
                        self::callablesMatch($entry['callback'], $callback)
                    );
                }
            )
        );
    }

    /**
     * Compare two callables for equality.
     *
     * PHP has no native callable equality operator, so we normalise both
     * sides to a comparable string/array form before comparing.
     *
     * @param callable $a
     * @param callable $b
     */
    private static function callablesMatch(callable $a, callable $b): bool
    {
        // Closures are only equal if they are the exact same object.
        if ($a instanceof \Closure || $b instanceof \Closure) {
            return $a === $b;
        }

        $normalize = static function (callable $c): string {
            if (is_string($c)) {
                return $c;
            }
            if (is_array($c)) {
                $class = is_object($c[0]) ? get_class($c[0]) : $c[0];
                return $class . '::' . $c[1];
            }
            // Invokable object.
            return get_class($c) . '::__invoke';
        };

        return $normalize($a) === $normalize($b);
    }
}

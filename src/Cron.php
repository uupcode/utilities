<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * WP-Cron scheduled event helpers.
 *
 * Usage:
 *
 *   Cron::add('my_sync', 'hourly', fn() => sync_data());
 *   Cron::remove('my_sync');
 *   Cron::isScheduled('my_sync');
 *   Cron::nextRun('my_sync');
 *
 *   Cron::addInterval('every_5_minutes', 300, 'Every 5 Minutes');
 */
final class Cron
{
    /**
     * Schedule an event and register its action callback.
     *
     * If the event is already scheduled it is not duplicated.
     */
    public static function add(string $hook, string $recurrence, callable $callback): void
    {
        // Register the callback that runs when the cron fires.
        add_action($hook, $callback);

        // Schedule if not already scheduled.
        add_action('init', static function () use ($hook, $recurrence): void {
            if (! wp_next_scheduled($hook)) {
                wp_schedule_event(time(), $recurrence, $hook);
            }
        });
    }

    /**
     * Unschedule all future runs of a hook.
     */
    public static function remove(string $hook): void
    {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp !== false) {
            wp_unschedule_event($timestamp, $hook);
        }
    }

    /**
     * Whether a hook is currently scheduled.
     */
    public static function isScheduled(string $hook): bool
    {
        return wp_next_scheduled($hook) !== false;
    }

    /**
     * Timestamp of the next run, or null if not scheduled.
     */
    public static function nextRun(string $hook): ?int
    {
        $ts = wp_next_scheduled($hook);
        return $ts !== false ? (int) $ts : null;
    }

    /**
     * Register a custom cron interval.
     *
     * @param string $key        Unique key, e.g. 'every_5_minutes'
     * @param int    $interval   Interval in seconds
     * @param string $display    Human-readable label
     */
    public static function addInterval(string $key, int $interval, string $display): void
    {
        add_filter('cron_schedules', static function (array $schedules) use ($key, $interval, $display): array {
            if (! isset($schedules[ $key ])) {
                $schedules[ $key ] = [
                    'interval' => $interval,
                    'display'  => $display,
                ];
            }
            return $schedules;
        });
    }
}

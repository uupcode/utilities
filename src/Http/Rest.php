<?php

declare(strict_types=1);

namespace UupCode\Utilities\Http;

/**
 * REST API route registration facade.
 *
 * Routes are collected and registered on rest_api_init automatically.
 *
 * Usage:
 *
 *   Rest::get('my-plugin/v1', 'items', [ItemController::class, 'index'])
 *       ->permission(fn() => current_user_can('manage_options'));
 *
 *   Rest::post('my-plugin/v1', 'items', [ItemController::class, 'store'])
 *       ->permission(fn() => is_user_logged_in())
 *       ->schema(['name' => ['type' => 'string', 'required' => true]]);
 *
 *   Rest::delete('my-plugin/v1', 'items/(?P<id>\d+)', fn($req) => new \WP_REST_Response(null, 204));
 */
final class Rest
{
    /** @var RestRoute[] */
    private static array $routes = [];
    private static bool  $hooked = false;

    /** @param callable|array<mixed> $callback */
    public static function get(string $namespace, string $route, callable|array $callback): RestRoute
    {
        return self::add($namespace, $route, 'GET', $callback);
    }

    /** @param callable|array<mixed> $callback */
    public static function post(string $namespace, string $route, callable|array $callback): RestRoute
    {
        return self::add($namespace, $route, 'POST', $callback);
    }

    /** @param callable|array<mixed> $callback */
    public static function put(string $namespace, string $route, callable|array $callback): RestRoute
    {
        return self::add($namespace, $route, 'PUT', $callback);
    }

    /** @param callable|array<mixed> $callback */
    public static function patch(string $namespace, string $route, callable|array $callback): RestRoute
    {
        return self::add($namespace, $route, 'PATCH', $callback);
    }

    /** @param callable|array<mixed> $callback */
    public static function delete(string $namespace, string $route, callable|array $callback): RestRoute
    {
        return self::add($namespace, $route, 'DELETE', $callback);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /**
     * @param callable|array<mixed> $callback
     */
    private static function add(
        string         $namespace,
        string         $route,
        string         $method,
        callable|array $callback,
    ): RestRoute {
        $restRoute = new RestRoute($namespace, $route, $method, $callback);

        self::$routes[] = $restRoute;
        self::ensureHook();

        return $restRoute;
    }

    private static function ensureHook(): void
    {
        if (self::$hooked) {
            return;
        }

        self::$hooked = true;

        add_action('rest_api_init', static function (): void {
            foreach (self::$routes as $route) {
                register_rest_route(
                    $route->getNamespace(),
                    '/' . ltrim($route->getRoute(), '/'),
                    $route->toArgs(),
                );
            }
        });
    }
}

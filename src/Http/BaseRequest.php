<?php

declare(strict_types=1);

namespace UupCode\Utilities\Http;

/**
 * Instantiable base request — typed data access with overridable authorization.
 *
 * Extend this class (or AjaxRequest) to define per-request authorization logic.
 *
 * Usage:
 *
 *   class CreatePostRequest extends AjaxRequest
 *   {
 *       public function authorize(): bool    { return current_user_can('edit_posts'); }
 *       public function nonceAction(): string { return 'create_post'; }
 *   }
 *
 *   Ajax::handle('create_post', function(CreatePostRequest $request) {
 *       $title = $request->string('title');
 *       $count = $request->int('count', 1);
 *   })->register();
 */
class BaseRequest
{
    // ─── Typed reads ──────────────────────────────────────────────────────────

    public function string(string $key, string $default = ''): string
    {
        return Request::string($key, $default);
    }

    public function int(string $key, int $default = 0): int
    {
        return Request::int($key, $default);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return Request::float($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return Request::bool($key, $default);
    }

    /** @return list<string> */
    public function array(string $key, array $default = []): array
    {
        return Request::array($key, $default);
    }

    public function fromPost(string $key, string $default = ''): string
    {
        return Request::fromPost($key, $default);
    }

    public function fromGet(string $key, string $default = ''): string
    {
        return Request::fromGet($key, $default);
    }

    // ─── Presence ─────────────────────────────────────────────────────────────

    public function has(string $key): bool
    {
        return Request::has($key);
    }

    public function missing(string $key): bool
    {
        return Request::missing($key);
    }

    public function filled(string $key): bool
    {
        return Request::filled($key);
    }

    // ─── Subset / all ─────────────────────────────────────────────────────────

    /** @return array<string, string> */
    public function only(string ...$keys): array
    {
        return Request::only(...$keys);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return Request::all();
    }

    // ─── Meta ─────────────────────────────────────────────────────────────────

    public function method(): string
    {
        return Request::method();
    }

    public function isPost(): bool
    {
        return Request::isPost();
    }

    public function isAjax(): bool
    {
        return Request::isAjax();
    }

    // ─── Authorization ────────────────────────────────────────────────────────

    /**
     * Override to restrict access to this request.
     * Called automatically before the handler callback executes.
     */
    public function authorize(): bool
    {
        return true;
    }
}

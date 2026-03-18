<?php

declare(strict_types=1);

namespace UupCode\Utilities\Http;

/**
 * AJAX handler registration facade.
 *
 * Usage:
 *
 *   Ajax::handle('my_action', function() {
 *       Ajax::json(['greeting' => 'Hello']);
 *   })->nonce('my-action');
 *
 *   Ajax::handle('public_action', fn() => Ajax::json(['ok' => true]))->public();
 *
 *   Ajax::json(['data' => $rows]);
 *   Ajax::error('Something went wrong', 400);
 */
final class Ajax
{
    /**
     * Register an AJAX handler for authenticated users (and optionally public).
     */
    public static function handle(string $action, callable $callback): AjaxHandler
    {
        return new AjaxHandler($action, $callback);
    }

    /**
     * Send a success JSON response and terminate.
     *
     * @param mixed $data
     */
    public static function json(mixed $data, int $statusCode = 200): void
    {
        wp_send_json($data, $statusCode);
    }

    /**
     * Send a success JSON response and terminate.
     *
     * @param mixed $data
     */
    public static function success(mixed $data, int $statusCode = 200): void
    {
        wp_send_json_success($data, $statusCode);
    }

    /**
     * Send an error JSON response and terminate.
     *
     * @param mixed $data
     */
    public static function error(mixed $data, int $statusCode = 400): void
    {
        wp_send_json_error($data, $statusCode);
    }
}

/**
 * Fluent builder returned by Ajax::handle().
 */
final class AjaxHandler
{
    private bool    $isPublic    = false;
    private ?string $nonceAction = null;
    private ?string $nonceField  = '_wpnonce';

    /**
     * @param \Closure|array<mixed>|string $callback
     */
    public function __construct(
        private readonly string   $action,
        private readonly \Closure|array|string $callback,
    ) {
    }

    /**
     * Also allow non-authenticated (logged-out) users to trigger this action.
     */
    public function public(): static
    {
        $this->isPublic = true;
        $this->register();
        return $this;
    }

    /**
     * Verify a nonce before running the callback.
     * The nonce value is read from $_REQUEST[$field].
     */
    public function nonce(string $action, string $field = '_wpnonce'): static
    {
        $this->nonceAction = $action;
        $this->nonceField  = $field;
        $this->register();
        return $this;
    }

    /**
     * Register hooks without additional options.
     * Called automatically by public() and nonce(); also call directly if neither is needed.
     */
    public function register(): static
    {
        $action   = $this->action;
        $callback = $this->buildCallback();

        add_action('wp_ajax_' . $action, $callback);

        if ($this->isPublic) {
            add_action('wp_ajax_nopriv_' . $action, $callback);
        }

        return $this;
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function buildCallback(): \Closure
    {
        $innerCallback  = $this->callback;
        $fluentNonce    = $this->nonceAction;
        $fluentNonceField = $this->nonceField;
        $requestClass   = $this->resolveRequestClass();

        return static function () use ($innerCallback, $fluentNonce, $fluentNonceField, $requestClass): void {
            $request = new $requestClass();

            // Request class nonce takes precedence over fluent .nonce() config.
            $nonceAction = $request->nonceAction() !== '' ? $request->nonceAction() : $fluentNonce;
            $nonceField  = $request->nonceField() !== '' ? $request->nonceField() : ($fluentNonceField ?? '_wpnonce');

            if ($nonceAction !== null && $nonceAction !== '') {
                $nonce = sanitize_text_field((string) ($_REQUEST[ $nonceField ] ?? ''));
                if (! wp_verify_nonce($nonce, $nonceAction)) {
                    wp_send_json_error([ 'message' => 'Invalid nonce.' ], 403);
                }
            }

            if (! $request->authorize()) {
                wp_send_json_error([ 'message' => 'Unauthorized.' ], 403);
            }

            ($innerCallback)($request);
        };
    }

    /**
     * Resolve the AjaxRequest subclass from the callback's first parameter type hint.
     * Falls back to AjaxRequest if no type hint or not a subclass of AjaxRequest.
     *
     * @return class-string<AjaxRequest>
     */
    private function resolveRequestClass(): string
    {
        try {
            $ref = match (true) {
                $this->callback instanceof \Closure => new \ReflectionFunction($this->callback),
                is_array($this->callback)         => new \ReflectionMethod($this->callback[0], $this->callback[1]),
                default                             => null,
            };

            if ($ref !== null) {
                $params = $ref->getParameters();
                if (! empty($params)) {
                    $type = $params[0]->getType();
                    if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                        $class = $type->getName();
                        if (is_a($class, AjaxRequest::class, true)) {
                            return $class;
                        }
                    }
                }
            }
        } catch (\ReflectionException) {
            // fall through
        }

        return AjaxRequest::class;
    }
}

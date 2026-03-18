<?php

declare(strict_types=1);

namespace UupCode\Utilities\Http;

/**
 * Fluent REST route builder returned by Rest::get/post/put/patch/delete.
 */
final class RestRoute
{
    private mixed $permissionCallback = null;
    /** @var array<string, mixed> */
    private array $schema    = [];
    /** @var array<string, mixed> */
    private array $extraArgs = [];

    public function __construct(
        private readonly string            $namespace,
        private readonly string            $route,
        private readonly string            $method,
        private readonly mixed $callback,
    ) {
    }

    /**
     * Set the permission callback for this route.
     */
    public function permission(callable $callback): static
    {
        $this->permissionCallback = $callback;
        return $this;
    }

    /**
     * Define the JSON schema for request args validation.
     *
     * @param array<string, mixed> $schema
     */
    public function schema(array $schema): static
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * Merge additional raw args into the route definition.
     *
     * @param array<string, mixed> $args
     */
    public function args(array $args): static
    {
        $this->extraArgs = $args;
        return $this;
    }

    /**
     * Build the args array for register_rest_route().
     *
     * @return array<string, mixed>
     */
    public function toArgs(): array
    {
        $args = [
            'methods'  => $this->method,
            'callback' => $this->callback,
        ];

        if ($this->permissionCallback !== null) {
            $args['permission_callback'] = $this->permissionCallback;
        } else {
            // Default: require authentication.
            $args['permission_callback'] = static fn () => is_user_logged_in();
        }

        if (! empty($this->schema)) {
            $args['args'] = $this->schema;
        }

        return array_merge($args, $this->extraArgs);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }
    public function getRoute(): string
    {
        return $this->route;
    }
}

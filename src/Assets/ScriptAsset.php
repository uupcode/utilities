<?php

declare(strict_types=1);

namespace UupCode\Utilities\Assets;

/**
 * Fluent builder for a WordPress script (wp_enqueue_script).
 *
 * Returned by Asset::script(). Call enqueue() or register() to finalise.
 */
final class ScriptAsset
{
    /** @var list<string> */
    private array $deps = [];

    private string|null $version = null;

    private bool $inFooter = false;

    /** @var list<array{object_name:string,data:array<mixed>}> */
    private array $localizedData = [];

    /** @var list<string> */
    private array $inlineScripts = [];

    private bool $adminOnly    = false;
    private bool $frontendOnly = false;

    public function __construct(
        private readonly string $handle,
        private readonly string $src,
    ) {
    }

    // ─── Builder ──────────────────────────────────────────────────────────────

    public function deps(string ...$handles): static
    {
        $this->deps = array_values($handles);
        return $this;
    }

    public function version(string $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function footer(): static
    {
        $this->inFooter = true;
        return $this;
    }

    /**
     * Localise data to a JS global variable.
     *
     * @param array<mixed> $data
     */
    public function localize(string $objectName, array $data): static
    {
        $this->localizedData[] = [ 'object_name' => $objectName, 'data' => $data ];
        return $this;
    }

    public function onlyAdmin(): static
    {
        $this->adminOnly = true;
        return $this;
    }

    public function onlyFrontend(): static
    {
        $this->frontendOnly = true;
        return $this;
    }

    public function addInline(string $script): static
    {
        $this->inlineScripts[] = $script;
        return $this;
    }

    // ─── Finalise ─────────────────────────────────────────────────────────────

    public function enqueue(): void
    {
        if (! $this->shouldLoad()) {
            return;
        }

        wp_enqueue_script($this->handle, $this->src, $this->deps, $this->version, $this->inFooter);
        $this->applyExtras();
    }

    public function register(): void
    {
        wp_register_script($this->handle, $this->src, $this->deps, $this->version, $this->inFooter);
        $this->applyExtras();
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function shouldLoad(): bool
    {
        if ($this->adminOnly && ! is_admin()) {
            return false;
        }
        if ($this->frontendOnly && is_admin()) {
            return false;
        }
        return true;
    }

    private function applyExtras(): void
    {
        foreach ($this->localizedData as $item) {
            wp_localize_script($this->handle, $item['object_name'], $item['data']);
        }

        foreach ($this->inlineScripts as $script) {
            wp_add_inline_script($this->handle, $script);
        }
    }
}

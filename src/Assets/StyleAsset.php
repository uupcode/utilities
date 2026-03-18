<?php

declare(strict_types=1);

namespace UupCode\Utilities\Assets;

/**
 * Fluent builder for a WordPress stylesheet (wp_enqueue_style).
 *
 * Returned by Asset::style(). Call enqueue() or register() to finalise.
 */
final class StyleAsset
{
    /** @var list<string> */
    private array $deps = [];

    private string|null $version = null;

    private string $media = 'all';

    /** @var list<string> */
    private array $inlineStyles = [];

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

    public function media(string $media): static
    {
        $this->media = $media;
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

    public function addInlineStyle(string $css): static
    {
        $this->inlineStyles[] = $css;
        return $this;
    }

    // ─── Finalise ─────────────────────────────────────────────────────────────

    public function enqueue(): void
    {
        if (! $this->shouldLoad()) {
            return;
        }

        wp_enqueue_style($this->handle, $this->src, $this->deps, $this->version, $this->media);
        $this->applyExtras();
    }

    public function register(): void
    {
        wp_register_style($this->handle, $this->src, $this->deps, $this->version, $this->media);
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
        foreach ($this->inlineStyles as $css) {
            wp_add_inline_style($this->handle, $css);
        }
    }
}

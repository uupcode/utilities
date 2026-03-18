<?php

declare(strict_types=1);

namespace UupCode\Utilities\PostTypes;

/**
 * Custom post type registration — fluent builder.
 *
 * Usage:
 *
 *   PostType::make('product', singular: 'Product', plural: 'Products')
 *       ->supports('title', 'editor', 'thumbnail')
 *       ->public()
 *       ->rewrite('products')
 *       ->menuIcon('dashicons-cart')
 *       ->register();
 *
 *   $pt = PostType::get('product');
 */
final class PostType
{
    /** @var array<string, static> */
    private static array $registry = [];

    private bool          $isPublic    = false;
    private array         $supports    = [ 'title', 'editor' ];
    private string|bool   $rewrite     = true;
    private string        $menuIcon    = 'dashicons-admin-post';
    private bool          $hasArchive  = false;
    private bool          $showInRest  = true;
    private string        $menuPosition = '';
    private array         $extraArgs   = [];

    private function __construct(
        private readonly string $slug,
        private readonly string $singular,
        private readonly string $plural,
    ) {
    }

    public static function make(string $slug, string $singular = '', string $plural = ''): static
    {
        $instance = new static(
            $slug,
            $singular ?: ucfirst($slug),
            $plural ?: ucfirst($slug) . 's',
        );
        return $instance;
    }

    public static function get(string $slug): ?static
    {
        return self::$registry[ $slug ] ?? null;
    }

    // ─── Builder ──────────────────────────────────────────────────────────────

    public function supports(string ...$features): static
    {
        $this->supports = $features;
        return $this;
    }

    public function public(): static
    {
        $this->isPublic = true;
        return $this;
    }

    public function rewrite(string $slug): static
    {
        $this->rewrite = $slug;
        return $this;
    }

    public function menuIcon(string $icon): static
    {
        $this->menuIcon = $icon;
        return $this;
    }

    public function hasArchive(bool $value = true): static
    {
        $this->hasArchive = $value;
        return $this;
    }

    public function showInRest(bool $value = true): static
    {
        $this->showInRest = $value;
        return $this;
    }

    public function args(array $args): static
    {
        $this->extraArgs = $args;
        return $this;
    }

    /**
     * Schedule registration on the init hook.
     */
    public function register(): static
    {
        self::$registry[ $this->slug ] = $this;

        $slug     = $this->slug;
        $singular = $this->singular;
        $plural   = $this->plural;
        $args     = $this->buildArgs();

        add_action('init', static function () use ($slug, $args): void {
            register_post_type($slug, $args);
        });

        return $this;
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function buildArgs(): array
    {
        $singular = $this->singular;
        $plural   = $this->plural;

        $labels = [
            'name'               => $plural,
            'singular_name'      => $singular,
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New ' . $singular,
            'edit_item'          => 'Edit ' . $singular,
            'new_item'           => 'New ' . $singular,
            'view_item'          => 'View ' . $singular,
            'search_items'       => 'Search ' . $plural,
            'not_found'          => 'No ' . strtolower($plural) . ' found.',
            'not_found_in_trash' => 'No ' . strtolower($plural) . ' found in Trash.',
            'all_items'          => 'All ' . $plural,
            'menu_name'          => $plural,
        ];

        $rewrite = is_string($this->rewrite)
            ? [ 'slug' => $this->rewrite ]
            : $this->rewrite;

        $defaults = [
            'labels'       => $labels,
            'public'       => $this->isPublic,
            'show_ui'      => true,
            'show_in_menu' => true,
            'show_in_rest' => $this->showInRest,
            'supports'     => $this->supports,
            'rewrite'      => $rewrite,
            'menu_icon'    => $this->menuIcon,
            'has_archive'  => $this->hasArchive,
        ];

        return array_merge($defaults, $this->extraArgs);
    }
}

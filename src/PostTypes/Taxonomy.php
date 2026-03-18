<?php

declare(strict_types=1);

namespace UupCode\Utilities\PostTypes;

/**
 * Custom taxonomy registration — fluent builder.
 *
 * Usage:
 *
 *   Taxonomy::make('product_cat', postTypes: 'product', singular: 'Category', plural: 'Categories')
 *       ->hierarchical()
 *       ->rewrite('product-category')
 *       ->register();
 */
final class Taxonomy
{
    /** @var array<string, static> */
    private static array $registry = [];

    /** @var string|string[] */
    private string|array $postTypes;
    private bool         $isHierarchical = false;
    private string|bool  $rewrite        = true;
    private bool         $showInRest     = true;
    private bool         $showAdminCol   = false;
    /** @var array<string, mixed> */
    private array        $extraArgs      = [];

    /**
     * @param string|array<string> $postTypes
     */
    private function __construct(
        private readonly string $slug,
        private readonly string $singular,
        private readonly string $plural,
        string|array $postTypes,
    ) {
        $this->postTypes = $postTypes;
    }

    /**
     * @param string|string[] $postTypes
     */
    public static function make(
        string       $slug,
        string|array $postTypes = [],
        string       $singular  = '',
        string       $plural    = '',
    ): static {
        return new static(
            $slug,
            $singular ?: ucfirst(str_replace([ '-', '_' ], ' ', $slug)),
            $plural ?: ucfirst(str_replace([ '-', '_' ], ' ', $slug)) . 's',
            $postTypes,
        );
    }

    public static function get(string $slug): ?static
    {
        return self::$registry[ $slug ] ?? null;
    }

    // ─── Builder ──────────────────────────────────────────────────────────────

    public function hierarchical(bool $value = true): static
    {
        $this->isHierarchical = $value;
        return $this;
    }

    public function rewrite(string $slug): static
    {
        $this->rewrite = $slug;
        return $this;
    }

    public function showInRest(bool $value = true): static
    {
        $this->showInRest = $value;
        return $this;
    }

    public function showAdminColumn(bool $value = true): static
    {
        $this->showAdminCol = $value;
        return $this;
    }

    /** @param array<string, mixed> $args */
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

        $slug      = $this->slug;
        $postTypes = $this->postTypes;
        $args      = $this->buildArgs();

        add_action('init', static function () use ($slug, $postTypes, $args): void {
            register_taxonomy($slug, $postTypes, $args);
        });

        return $this;
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function buildArgs(): array
    {
        $singular = $this->singular;
        $plural   = $this->plural;

        $labels = [
            'name'              => $plural,
            'singular_name'     => $singular,
            'search_items'      => 'Search ' . $plural,
            'all_items'         => 'All ' . $plural,
            'parent_item'       => 'Parent ' . $singular,
            'parent_item_colon' => 'Parent ' . $singular . ':',
            'edit_item'         => 'Edit ' . $singular,
            'update_item'       => 'Update ' . $singular,
            'add_new_item'      => 'Add New ' . $singular,
            'new_item_name'     => 'New ' . $singular . ' Name',
            'menu_name'         => $plural,
        ];

        $rewrite = is_string($this->rewrite)
            ? [ 'slug' => $this->rewrite ]
            : $this->rewrite;

        $defaults = [
            'labels'            => $labels,
            'hierarchical'      => $this->isHierarchical,
            'show_ui'           => true,
            'show_in_rest'      => $this->showInRest,
            'show_admin_column' => $this->showAdminCol,
            'rewrite'           => $rewrite,
        ];

        return array_merge($defaults, $this->extraArgs);
    }
}

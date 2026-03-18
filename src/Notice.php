<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * Admin notice builder.
 *
 * Usage:
 *
 *   Notice::success('Settings saved!')->dismissible()->show();
 *   Notice::error('Invalid key')->onPage('plugins.php')->show();
 *   Notice::warning('Cache is stale')->show();
 *   Notice::info('New version available')->dismissible()->show();
 */
final class Notice
{
    // ─── Factory ──────────────────────────────────────────────────────────────

    public static function success(string $message): AdminNotice
    {
        return new AdminNotice($message, 'success');
    }

    public static function error(string $message): AdminNotice
    {
        return new AdminNotice($message, 'error');
    }

    public static function warning(string $message): AdminNotice
    {
        return new AdminNotice($message, 'warning');
    }

    public static function info(string $message): AdminNotice
    {
        return new AdminNotice($message, 'info');
    }
}

/**
 * Fluent builder returned by Notice::* factory methods.
 */
final class AdminNotice
{
    private bool    $isDismissible = false;
    private ?string $limitPage     = null;

    public function __construct(
        private readonly string $message,
        private readonly string $type,
    ) {
    }

    /**
     * Add the is-dismissible class so WordPress renders an ✕ button.
     */
    public function dismissible(): static
    {
        $this->isDismissible = true;
        return $this;
    }

    /**
     * Only display the notice on a specific admin page ($pagenow).
     */
    public function onPage(string $pageHook): static
    {
        $this->limitPage = $pageHook;
        return $this;
    }

    /**
     * Hook the notice into admin_notices so it renders automatically.
     */
    public function show(): void
    {
        // Capture values before the closure runs (potentially much later).
        $message       = $this->message;
        $type          = $this->type;
        $isDismissible = $this->isDismissible;
        $limitPage     = $this->limitPage;

        add_action('admin_notices', static function () use ($message, $type, $isDismissible, $limitPage): void {
            global $pagenow;

            if ($limitPage !== null && $pagenow !== $limitPage) {
                return;
            }

            $classes  = 'notice notice-' . esc_attr($type);
            $classes .= $isDismissible ? ' is-dismissible' : '';

            printf(
                '<div class="%s"><p>%s</p></div>',
                esc_attr($classes),
                wp_kses_post($message)
            );
        });
    }
}

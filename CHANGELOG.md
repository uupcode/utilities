# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `Hook` — static action/filter registry with full introspection
- `Plugin` — lifecycle and path/URL helpers
- `ServiceProvider` — abstract base with PHP attribute-driven hook registration (`#[Action]`, `#[Filter]`)
- `Cache` — WordPress Object Cache facade with grouped interface and batch operations
- `Cron` — WP-Cron scheduling helpers with custom interval support
- `Meta` — unified post/user/term/comment meta facade
- `Notice` — fluent admin notice builder
- `Option` — WordPress options facade
- `Shortcode` — shortcode registration helpers
- `View` — template rendering helper
- `Database\DB` — fluent query builder facade
- `Database\QueryBuilder` — safe, injection-resistant SQL query builder wrapping `$wpdb`
- `Database\Expression` — raw SQL expression wrapper
- `Database\Model` — abstract base model with automatic column casting (array, object, int, float, bool, string)
- `Http\Request` — sanitized static request facade
- `Http\BaseRequest` — instantiable request with overridable authorization
- `Http\AjaxRequest` — AJAX-specific request with nonce contract
- `Http\Ajax` — AJAX handler registration with reflection-based request injection
- `Http\Http` — outbound HTTP client wrapping the WordPress HTTP API
- `Http\Rest` — REST API route registration facade
- `Assets\Asset` — fluent script/style enqueue builder with asset manifest support
- `Logging\Log` — multi-channel PSR-3 logger (errorlog, single file, daily rotating)
- `PostTypes\PostType` — fluent custom post type builder
- `PostTypes\Taxonomy` — fluent taxonomy builder
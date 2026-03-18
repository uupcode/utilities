# UupCode Utilities

[![CI](https://github.com/uupcode/utilities/actions/workflows/ci.yml/badge.svg)](https://github.com/uupcode/utilities/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/uupcode/utilities.svg)](https://packagist.org/packages/uupcode/utilities)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-green)](LICENSE)

Facades for WordPress plugin development. Covers database queries, hooks, AJAX, REST API, asset enqueueing, logging, caching, meta, post types, cron, and more — all with a clean, fluent API and no external runtime dependencies.

## Requirements

- PHP 8.1+
- WordPress 6.0+

## Installation

```bash
composer require uupcode/utilities
```

## Quick Start

```php
use UupCode\Utilities\Plugin;
use UupCode\Utilities\ServiceProvider;
use UupCode\Utilities\Attributes\Action;
use UupCode\Utilities\Assets\Asset;
use UupCode\Utilities\Http\Ajax;
use UupCode\Utilities\Http\AjaxRequest;

// In your plugin's main file:
Plugin::boot(__FILE__);
add_action('plugins_loaded', fn() => (new MyServiceProvider())->register());

// A service provider with automatic hook registration via attributes:
final class MyServiceProvider extends ServiceProvider
{
    #[Action('wp_enqueue_scripts')]
    public function enqueue(): void
    {
        $asset = require Plugin::path('build/index.asset.php');
        Asset::script('my-plugin', Plugin::url('build/index.js'))
            ->deps(...$asset['dependencies'])
            ->version($asset['version'])
            ->footer()
            ->enqueue();
    }
}

// An AJAX handler with nonce verification and authorization:
final class SaveItemRequest extends AjaxRequest
{
    public function authorize(): bool    { return is_user_logged_in(); }
    public function nonceAction(): string { return 'save_item'; }
}

Ajax::handle('my_plugin_save', function (SaveItemRequest $request) {
    $name = $request->string('name');
    Ajax::json(['saved' => true, 'name' => $name]);
})->register();
```

---

## API Reference

### ServiceProvider & Attributes

Extend `ServiceProvider` and annotate public methods with `#[Action]` or `#[Filter]`. Calling `register()` uses reflection to wire everything up automatically.

```php
use UupCode\Utilities\ServiceProvider;
use UupCode\Utilities\Attributes\Action;
use UupCode\Utilities\Attributes\Filter;

final class MyProvider extends ServiceProvider
{
    #[Action('init')]
    public function onInit(): void { }

    #[Action('save_post', priority: 20, args: 2)]
    public function onSave(int $id, \WP_Post $post): void { }

    #[Filter('the_content')]
    public function filterContent(string $content): string { return $content; }
}

(new MyProvider())->register();
```

Providers that manage their own hooks (Ajax, PostType, Cron) should override `register()` directly instead of using attributes.

---

### Hook

Static wrapper around `add_action` / `add_filter` with a full registry for introspection and testing.

```php
use UupCode\Utilities\Hook;

Hook::action('wp_head', [MyClass::class, 'output']);
Hook::action('save_post', $callback, priority: 20, args: 2);
Hook::filter('the_content', fn($c) => $c . '<footer>...</footer>');
Hook::remove('wp_head', [MyClass::class, 'output']);
Hook::removeFilter('the_content', $callback);

Hook::all();           // every registered entry
Hook::actions();       // only actions
Hook::filters();       // only filters
Hook::named('init');   // entries for a specific hook
Hook::has('init');     // bool
Hook::count();         // total
Hook::flush();         // clear registry (useful in tests)
```

---

### Plugin

Lifecycle callbacks and path/URL helpers. Call `Plugin::boot(__FILE__)` once in your main plugin file.

```php
use UupCode\Utilities\Plugin;

Plugin::boot(__FILE__);
Plugin::onActivate(fn() => install_tables());
Plugin::onDeactivate(fn() => flush_rewrite_rules());
Plugin::onUninstall(fn() => drop_tables());

Plugin::path('build/index.asset.php'); // absolute filesystem path
Plugin::url('build/index.js');         // public URL
Plugin::version();                     // reads Version header from plugin file
Plugin::basename();                    // e.g. my-plugin/my-plugin.php
Plugin::isActive('woocommerce/woocommerce.php');
```

---

### Assets

Fluent builder for `wp_enqueue_script` and `wp_enqueue_style`. Integrates with the `@wordpress/scripts` asset manifest.

```php
use UupCode\Utilities\Assets\Asset;

$asset = require Plugin::path('build/index.asset.php');

Asset::script('my-plugin', Plugin::url('build/index.js'))
    ->deps(...$asset['dependencies'])
    ->version($asset['version'])
    ->footer()
    ->localize('myPlugin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('my-action'),
    ])
    ->enqueue();

Asset::style('my-plugin', Plugin::url('build/index.css'))
    ->version($asset['version'])
    ->enqueue();

// Conditional loading
Asset::script('my-admin', Plugin::url('build/admin.js'))->onlyAdmin()->enqueue();
Asset::script('my-front', Plugin::url('build/index.js'))->onlyFrontend()->enqueue();

// Register only, enqueue later
Asset::script('my-plugin', $src)->register();
```

---

### Database

#### DB — Query Builder Facade

All user-supplied values go through `$wpdb->prepare()`. Column and table identifiers are validated and backtick-quoted. The only escape hatch is `DB::raw()`, whose safety is the caller's responsibility.

```php
use UupCode\Utilities\Database\DB;

// SELECT
DB::table('orders')->where('status', 'paid')->orderBy('created_at', 'DESC')->limit(10)->get();
DB::table('orders')->where('ID', 5)->first();
DB::table('orders')->where('status', 'paid')->count();
DB::table('orders')->where('status', 'paid')->sum('amount');
DB::table('options')->where('option_name', 'siteurl')->value('option_value');

// WHERE variants
->whereIn('status', ['paid', 'pending'])
->whereNull('deleted_at')
->whereBetween('amount', 10, 500)
->whereLike('title', 'hello')
->orWhere('status', 'refunded')
->whereRaw('YEAR(created_at) = %d', [2026])

// Write
DB::table('orders')->insert(['user_id' => 1, 'status' => 'paid']);
DB::table('orders')->where('ID', 5)->update(['status' => 'refunded']);
DB::table('orders')->where('ID', 5)->update(['views' => DB::raw('views + 1')]);
DB::table('orders')->where('status', 'cancelled')->delete();

// Transactions
DB::transaction(function () {
    DB::table('accounts')->where('id', 1)->update(['balance' => DB::raw('balance - 100')]);
    DB::table('accounts')->where('id', 2)->update(['balance' => DB::raw('balance + 100')]);
});

// Raw
DB::select('SELECT * FROM wp_posts WHERE ID = %d', [5]);
DB::statement('ALTER TABLE `wp_my_table` ADD INDEX (`user_id`)');
```

#### Model — Base Model with Casting

```php
use UupCode\Utilities\Database\Model;

class OrderModel extends Model
{
    protected static string $table = 'my_orders'; // wp_ prefix applied automatically

    protected static array $casts = [
        'meta'     => 'array',  // JSON string → PHP array
        'settings' => 'object', // JSON string → stdClass
        'amount'   => 'float',
        'is_paid'  => 'bool',
        'user_id'  => 'int',
    ];

    public static function paid(): array
    {
        return static::hydrate(
            static::query()->where('status', 'paid')->orderBy('created_at', 'DESC')->get()
        );
    }
}

OrderModel::all();   // list of objects, casts applied
OrderModel::find(5); // ?object, casts applied
OrderModel::paid();  // custom scoped query
```

---

### Http — Inbound Requests

#### Request (static)

Sanitized reads from `$_REQUEST` / `$_POST` / `$_GET`.

```php
use UupCode\Utilities\Http\Request;

Request::string('name');          // sanitize_text_field
Request::int('page', 1);
Request::float('price');
Request::bool('enabled');
Request::array('ids');
Request::fromPost('field');
Request::fromGet('tab');
Request::has('name');             // bool
Request::filled('name');          // present and non-empty
Request::only('name', 'email');   // ['name' => ..., 'email' => ...]
Request::all();                   // all sanitized
Request::method();                // 'GET' | 'POST' | ...
Request::isPost();
Request::isAjax();
Request::verifyNonce('action');   // throws RuntimeException on failure
```

#### BaseRequest (instantiable)

Extend to add per-request authorization. All `Request::*` methods are available as instance methods.

```php
use UupCode\Utilities\Http\BaseRequest;

class MyRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return current_user_can('edit_posts');
    }
}
```

#### AjaxRequest + Ajax

Define one request class per handler. `Ajax::handle()` reflects the callback's first parameter type, instantiates the request class, verifies the nonce, and calls `authorize()` — all automatically.

```php
use UupCode\Utilities\Http\AjaxRequest;
use UupCode\Utilities\Http\Ajax;

final class DeleteItemRequest extends AjaxRequest
{
    public function authorize(): bool    { return current_user_can('delete_posts'); }
    public function nonceAction(): string { return 'delete_item'; }
}

Ajax::handle('my_delete_item', function (DeleteItemRequest $request) {
    $id = $request->int('id');
    Ajax::json(['deleted' => true]);
})->register();

// Public (unauthenticated) handler
Ajax::handle('my_public', function (AjaxRequest $request) {
    Ajax::json(['ok' => true]);
})->public();
```

**Response helpers:**
```php
Ajax::json(['key' => 'value']);            // wp_send_json
Ajax::success(['key' => 'value']);         // wp_send_json_success
Ajax::error('Something went wrong', 400); // wp_send_json_error
```

---

### Http — Outbound Client

Fluent wrapper around the WordPress HTTP API (`wp_remote_*`).

```php
use UupCode\Utilities\Http\Http;

$res = Http::get('https://api.example.com/items')
    ->withHeader('Authorization', 'Bearer ' . $token)
    ->withQuery(['page' => 2, 'per_page' => 10])
    ->timeout(15)
    ->send();

$res->ok();     // bool — 2xx
$res->status(); // int
$res->body();   // string
$res->json();   // array — throws RuntimeException if not valid JSON
$res->header('content-type');

Http::post('https://api.example.com/items')
    ->withJson(['name' => 'Widget'])
    ->send()
    ->throw(); // throws RuntimeException if not 2xx

Http::get($url)->safe()->send();          // uses wp_safe_remote_*
Http::get($url)->sslVerify(false)->send();
```

---

### REST API

Routes are collected and registered on `rest_api_init` automatically.

```php
use UupCode\Utilities\Http\Rest;

Rest::get('my-plugin/v1', 'items', [ItemController::class, 'index'])
    ->permission(fn() => current_user_can('manage_options'));

Rest::post('my-plugin/v1', 'items', function (\WP_REST_Request $req) {
    return new \WP_REST_Response(['created' => true], 201);
})->permission(fn() => is_user_logged_in())
  ->schema(['name' => ['type' => 'string', 'required' => true]]);

Rest::delete('my-plugin/v1', 'items/(?P<id>\d+)', $callback);
```

Default `permission_callback` is `is_user_logged_in()` when none is set.

---

### Logging

Multi-channel PSR-3 logger. Zero-config by default — writes via `error_log()` into `wp-content/debug.log`.

```php
use UupCode\Utilities\Logging\Log;

Log::info('User logged in', ['user_id' => 5]);
Log::error('Payment failed', ['order_id' => 99]);
// Levels: debug, info, notice, warning, error, critical, alert, emergency

// Configure named channels once during plugin boot:
Log::configure([
    'default'  => 'app',
    'channels' => [
        'app' => [
            'driver' => 'single',
            'path'   => WP_CONTENT_DIR . '/logs/my-plugin.log',
            'level'  => 'debug',
        ],
        'payments' => [
            'driver' => 'daily',
            'path'   => WP_CONTENT_DIR . '/logs/payments.log',
            'days'   => 14,
            'level'  => 'warning',
        ],
    ],
]);

Log::channel('payments')->error('Stripe failed', ['payload' => $data]);
```

**Drivers:** `errorlog` (default), `single` (single file), `daily` (date-stamped + auto-pruning).

---

### Cache

Facade for the WordPress Object Cache with grouped interface and batch operations.

```php
use UupCode\Utilities\Cache;

Cache::get('key', 'my-plugin', $default);
Cache::set('key', $value, 'my-plugin', 3600);
Cache::delete('key', 'my-plugin');
Cache::has('key', 'my-plugin');

// Get-or-compute
Cache::remember('posts', 'my-plugin', 3600, fn() => DB::table('posts')->get());

// Grouped — avoids repeating the group name
$c = Cache::group('my-plugin');
$c->remember('posts', 600, fn() => fetch_posts());
$c->set('key', $value, 300);
$c->flush(); // requires a persistent object cache (e.g. Redis)
```

---

### Meta

Unified facade for post, user, term, and comment meta.

```php
use UupCode\Utilities\Meta;

Meta::post(5)->get('_price');
Meta::post(5)->set('_price', 99.99);
Meta::post(5)->has('_price');
Meta::post(5)->delete('_price');
Meta::post(5)->all('_tag');                          // all values for a key
Meta::post(5)->every();                              // all meta for the object
Meta::post(5)->setMany(['_price' => 99, '_sku' => 'ABC']);

Meta::user($userId)->get('_billing_country');
Meta::term($termId)->set('_icon', 'star');
Meta::comment($commentId)->get('_rating');
```

---

### Post Types

Fluent builders for custom post types and taxonomies. Registration is deferred to the `init` hook automatically.

```php
use UupCode\Utilities\PostTypes\PostType;
use UupCode\Utilities\PostTypes\Taxonomy;

PostType::make('product', singular: 'Product', plural: 'Products')
    ->supports('title', 'editor', 'thumbnail')
    ->public()
    ->rewrite('products')
    ->menuIcon('dashicons-cart')
    ->hasArchive()
    ->register();

Taxonomy::make('product_cat', postTypes: 'product', singular: 'Category', plural: 'Categories')
    ->hierarchical()
    ->rewrite('product-category')
    ->showAdminColumn()
    ->register();
```

---

### Cron

WP-Cron scheduling with a clean API.

```php
use UupCode\Utilities\Cron;

Cron::add('my_sync', 'hourly', fn() => sync_data());
Cron::add('my_cleanup', 'daily', [CleanupJob::class, 'run']);
Cron::remove('my_sync');
Cron::isScheduled('my_sync'); // bool
Cron::nextRun('my_sync');     // ?int timestamp

// Custom interval
Cron::addInterval('every_5_minutes', 300, 'Every 5 Minutes');
```

---

### Shortcode

```php
use UupCode\Utilities\Shortcode;

Shortcode::register('my_button', function (array $atts, string $content = ''): string {
    $atts = shortcode_atts(['label' => 'Click', 'url' => '#'], $atts);
    return sprintf('<a href="%s">%s</a>', esc_url($atts['url']), esc_html($atts['label']));
});

Shortcode::remove('embed');
Shortcode::exists('my_button'); // bool
```

---

### Notice

Fluent admin notice builder.

```php
use UupCode\Utilities\Notice;

Notice::success('Settings saved.')->dismissible()->show();
Notice::error('Invalid API key.')->show();
Notice::warning('Cache is stale.')->onPage('plugins.php')->show();
Notice::info('A new version is available.')->dismissible()->show();
```

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
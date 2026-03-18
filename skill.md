# UupCode Utilities — AI Agent Skill Reference

Package: `uupcode/utilities`
Root namespace: `UupCode\Utilities`
PSR-4 root: `src/`

---

## Namespace Map

```
UupCode\Utilities                  src/             Hook, Plugin, ServiceProvider, Cache, Cron,
                                                 Debug, Mail, Meta, Notice, Option, Shortcode,
                                                 User, View
UupCode\Utilities\Attributes       src/Attributes/  Action, Filter
UupCode\Utilities\Assets           src/Assets/      Asset, ScriptAsset, StyleAsset
UupCode\Utilities\Database         src/Database/    DB, QueryBuilder, Expression, Model
UupCode\Utilities\Http             src/Http/        Request, BaseRequest, AjaxRequest, Ajax,
                                                 Http, HttpResponse, Rest, RestRoute
UupCode\Utilities\Logging          src/Logging/     Log, LogChannel, LogDriver, LogLevel,
                                                 ErrorLogDriver, DailyFileLogDriver,
                                                 SingleFileLogDriver
UupCode\Utilities\PostTypes        src/PostTypes/   PostType, Taxonomy
```

---

## Scaffolded Plugin Structure

A scaffolded plugin (`wp uup-plugin scaffold`) produces this layout:

```
plugin-slug/
├── plugin-slug.php          # Main file — calls Plugin::boot(__FILE__)
├── src/
│   ├── Plugin.php           # Lifecycle class — extends BasePlugin via composition
│   ├── Models/
│   │   └── ExampleModel.php
│   ├── Http/Requests/
│   │   └── ExampleRequest.php
│   └── Providers/
│       ├── HookServiceProvider.php
│       ├── AssetServiceProvider.php
│       ├── AdminServiceProvider.php
│       ├── AjaxServiceProvider.php
│       ├── RestServiceProvider.php
│       ├── PostTypeServiceProvider.php
│       ├── CronServiceProvider.php
│       ├── BlockServiceProvider.php
│       └── ShortcodeServiceProvider.php
├── resources/               # JS/CSS source (compiled by @wordpress/scripts)
│   ├── index.js
│   ├── index.css
│   ├── admin.js
│   ├── admin.css
│   └── blocks/example/
│       ├── block.json
│       ├── index.js
│       ├── editor.css
│       └── style.css
├── build/                   # Compiled output (gitignored)
├── languages/               # .pot and .json translation files
├── webpack.config.js
└── package.json
```

### Plugin boot (plugin-slug.php)

```php
use Vendor\PluginName\Plugin;
Plugin::boot(__FILE__);
```

### Plugin class (src/Plugin.php)

Uses `UupCode\Utilities\Plugin` as `BasePlugin` via composition:

```php
use UupCode\Utilities\Plugin as BasePlugin;

final class Plugin {
    private const MIN_PHP = '8.1';
    private const MIN_WP  = '6.0';

    public static function boot(string $file): void {
        BasePlugin::boot($file);
        // Activation/deactivation hooks registered immediately (before plugins_loaded)
        BasePlugin::onActivate([static::class, 'activate']);
        BasePlugin::onDeactivate([static::class, 'deactivate']);
        BasePlugin::onUninstall([static::class, 'uninstall']);
        add_action('plugins_loaded', [static::class, 'init']);
    }

    public static function init(): void {
        load_plugin_textdomain('plugin-slug', false, dirname(BasePlugin::basename()) . '/languages');
        (new HookServiceProvider())->register();
        (new AssetServiceProvider())->register();
        // ... boot other providers
    }
}
```

---

## ServiceProvider + Attributes

**`UupCode\Utilities\ServiceProvider`** — extend to create providers.
**`UupCode\Utilities\Attributes\Action`** / **`Filter`** — auto-register hooks via PHP attributes.

```php
use UupCode\Utilities\ServiceProvider;
use UupCode\Utilities\Attributes\Action;
use UupCode\Utilities\Attributes\Filter;

final class MyServiceProvider extends ServiceProvider {
    #[Action('init')]
    public function onInit(): void { }

    #[Action('save_post', priority: 20, args: 2)]
    public function onSavePost(int $postId, \WP_Post $post): void { }

    #[Filter('the_content')]
    public function filterContent(string $content): string {
        return $content;
    }
}
```

`register()` uses reflection to find all `#[Action]` and `#[Filter]` attributes and
registers them via `Hook::action()` / `Hook::filter()`. Call `(new MyProvider())->register()`.

Providers that manage their own hooks (Ajax, PostType, Cron) override `register()` directly
instead of using attributes.

---

## Hook

**`UupCode\Utilities\Hook`** — static wrapper around `add_action` / `add_filter` with a registry.

```php
use UupCode\Utilities\Hook;

Hook::action('wp_head', [MyClass::class, 'output']);
Hook::action('save_post', $callback, priority: 20, args: 2);
Hook::filter('the_content', fn($c) => $c . '<p>Footer</p>');
Hook::remove('wp_head', [MyClass::class, 'output']);
Hook::removeFilter('the_content', $callback);

Hook::all();          // all registered entries
Hook::actions();      // only actions
Hook::filters();      // only filters
Hook::named('init');  // entries for one hook
Hook::has('init');    // bool
Hook::flush();        // clear registry (tests)
```

---

## Plugin (BasePlugin)

**`UupCode\Utilities\Plugin`** — path/URL helpers and lifecycle hooks.
Call `Plugin::boot(__FILE__)` once in the plugin root file.

```php
use UupCode\Utilities\Plugin;

Plugin::boot(__FILE__);
Plugin::onActivate(fn() => install_tables());
Plugin::onDeactivate(fn() => flush_rewrite_rules());
Plugin::onUninstall(fn() => drop_tables());

Plugin::path('build/index.asset.php');   // absolute filesystem path
Plugin::url('build/index.js');           // public URL
Plugin::version();                       // reads Version header
Plugin::basename();                      // e.g. my-plugin/my-plugin.php
Plugin::isActive('woocommerce/woocommerce.php');
```

---

## Assets

**`UupCode\Utilities\Assets\Asset`** — factory for script/style builders.

```php
use UupCode\Utilities\Assets\Asset;

// Script
Asset::script('my-plugin', Plugin::url('build/index.js'))
    ->deps('wp-element', 'jquery')
    ->version(Plugin::version())
    ->footer()
    ->localize('myPlugin', ['ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('my-action')])
    ->enqueue();

// Style
Asset::style('my-plugin', Plugin::url('build/index.css'))
    ->deps('wp-components')
    ->version(Plugin::version())
    ->enqueue();

// Conditional loading
Asset::script('my-admin', Plugin::url('build/admin.js'))
    ->onlyAdmin()   // only in wp-admin
    ->enqueue();

Asset::script('my-front', Plugin::url('build/index.js'))
    ->onlyFrontend() // only on frontend
    ->enqueue();

// Register without enqueuing (for conditional use)
Asset::script('my-plugin', $src)->register();
wp_enqueue_script('my-plugin'); // enqueue later when needed
```

**Asset manifest pattern** (with `@wordpress/scripts`):

```php
$asset = require Plugin::path('build/index.asset.php');
// $asset = ['dependencies' => [...], 'version' => '...']
Asset::script('my-plugin', Plugin::url('build/index.js'))
    ->deps(...$asset['dependencies'])
    ->version($asset['version'])
    ->footer()
    ->enqueue();
```

---

## Database

### DB — Query Builder facade

**`UupCode\Utilities\Database\DB`**

```php
use UupCode\Utilities\Database\DB;

// SELECT
$rows = DB::table('my_orders')
    ->where('status', 'paid')
    ->where('amount', '>', 100)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();                   // list<object>

$row  = DB::table('my_orders')->find(5);           // by ID
$row  = DB::table('my_orders')->where('slug', 'foo')->first();  // or null
$val  = DB::table('options')->where('option_name', 'foo')->value('option_value');

// Aggregates
$count = DB::table('my_orders')->where('status', 'paid')->count();
$total = DB::table('my_orders')->sum('amount');

// WHERE variants
->whereIn('status', ['paid', 'pending'])
->whereNull('deleted_at')
->whereBetween('amount', 10, 100)
->whereLike('title', 'hello')
->orWhere('status', 'refunded')
->whereRaw('YEAR(created_at) = %d', [2026])

// INSERT
$id = DB::table('my_orders')->insert(['user_id' => 1, 'status' => 'paid']);
DB::table('my_orders')->insertBatch([
    ['user_id' => 1, 'status' => 'paid'],
    ['user_id' => 2, 'status' => 'pending'],
]);

// UPDATE
DB::table('my_orders')->where('ID', 5)->update(['status' => 'refunded']);
DB::table('my_orders')->where('ID', 5)->update(['views' => DB::raw('views + 1')]);

// DELETE
DB::table('my_orders')->where('status', 'cancelled')->delete();

// Transactions
DB::transaction(function () {
    DB::table('accounts')->where('id', 1)->update(['balance' => DB::raw('balance - 100')]);
    DB::table('accounts')->where('id', 2)->update(['balance' => DB::raw('balance + 100')]);
});

// Raw queries (when builder is insufficient)
DB::select('SELECT * FROM wp_posts WHERE ID = %d', [5]);
DB::scalar('SELECT COUNT(*) FROM wp_posts WHERE post_status = %s', ['publish']);
DB::statement('ALTER TABLE `wp_my_table` ADD INDEX (`user_id`)');
```

### Model — Base model with casting

**`UupCode\Utilities\Database\Model`**

```php
use UupCode\Utilities\Database\Model;

class OrderModel extends Model {
    protected static string $table = 'myplugin_orders'; // wp_ prefix auto-added

    protected static array $casts = [
        'meta'     => 'array',   // JSON string → PHP array
        'settings' => 'object',  // JSON string → stdClass
        'amount'   => 'float',
        'is_paid'  => 'bool',
        'user_id'  => 'int',
    ];
}

OrderModel::all();              // list<object>, casts applied
OrderModel::find(5);            // ?object, casts applied
OrderModel::table();            // 'wp_myplugin_orders'

// Custom queries — use query() + hydrate() to apply casts
class OrderModel extends Model {
    public static function paid(): array {
        return static::hydrate(
            static::query()->where('status', 'paid')->orderBy('created_at', 'DESC')->get()
        );
    }
}
```

---

## Http — Inbound Requests

### Request (static facade)

**`UupCode\Utilities\Http\Request`** — sanitized reads from `$_REQUEST`.

```php
use UupCode\Utilities\Http\Request;

Request::string('name');             // sanitize_text_field from $_REQUEST
Request::int('page', 1);
Request::float('price', 0.0);
Request::bool('enabled', false);
Request::array('ids', []);
Request::fromPost('name');
Request::fromGet('tab');
Request::has('name');                // bool
Request::filled('name');             // present and non-empty
Request::only('name', 'email');      // ['name' => ..., 'email' => ...]
Request::all();                      // all sanitized
Request::method();                   // 'GET' | 'POST' | ...
Request::isPost();
Request::isAjax();
Request::verifyNonce('my-action');   // throws RuntimeException on failure
```

### BaseRequest (instantiable)

**`UupCode\Utilities\Http\BaseRequest`** — same API as `Request` but as an instance.
Extend to add authorization logic.

```php
use UupCode\Utilities\Http\BaseRequest;

class MyRequest extends BaseRequest {
    public function authorize(): bool {
        return current_user_can('edit_posts');
    }
}
```

### AjaxRequest + Ajax

**`UupCode\Utilities\Http\AjaxRequest`** — extends `BaseRequest`, adds nonce contract.
**`UupCode\Utilities\Http\Ajax`** — AJAX handler registration facade.

Define one request class per handler:

```php
use UupCode\Utilities\Http\AjaxRequest;

final class DeleteItemRequest extends AjaxRequest {
    public function authorize(): bool {
        return current_user_can('delete_posts');
    }

    public function nonceAction(): string {
        return 'delete_item';
    }

    // Optional: override nonce field (default: '_wpnonce')
    public function nonceField(): string {
        return '_wpnonce';
    }
}
```

Register handlers in `AjaxServiceProvider`:

```php
use UupCode\Utilities\Http\Ajax;

// Type-hint the request class → automatically injected, nonce verified, authorize() checked
Ajax::handle('myplugin_delete_item', function(DeleteItemRequest $request) {
    $id = $request->int('id');
    Ajax::json(['deleted' => true]);
})->register();

// Public (unauthenticated) handler
Ajax::handle('myplugin_public', function(AjaxRequest $request) {
    Ajax::json(['ok' => true]);
})->public();

// Fluent nonce without a request class
Ajax::handle('myplugin_simple', function(AjaxRequest $request) {
    Ajax::json(['ok' => true]);
})->nonce('myplugin_simple');
```

**Ajax response methods:**

```php
Ajax::json(['key' => 'value']);            // wp_send_json — any shape
Ajax::success(['key' => 'value']);         // wp_send_json_success
Ajax::error('Something went wrong', 400); // wp_send_json_error
```

**How injection works:** `Ajax::handle()` reflects the callback's first parameter type.
If it's a subclass of `AjaxRequest`, that class is instantiated and passed in.
Nonce from the request class takes precedence over fluent `.nonce()`.

### Rest

**`UupCode\Utilities\Http\Rest`** — REST route registration.

```php
use UupCode\Utilities\Http\Rest;

Rest::get('myplugin/v1', 'items', [ItemController::class, 'index'])
    ->permission(fn() => current_user_can('manage_options'));

Rest::post('myplugin/v1', 'items', function(\WP_REST_Request $req) {
    return new \WP_REST_Response(['created' => true], 201);
})->permission(fn() => is_user_logged_in())
  ->schema(['name' => ['type' => 'string', 'required' => true]]);

Rest::delete('myplugin/v1', 'items/(?P<id>\d+)', $callback);
Rest::put('myplugin/v1', 'items/(?P<id>\d+)', $callback);
Rest::patch('myplugin/v1', 'items/(?P<id>\d+)', $callback);
```

Routes are collected and registered on `rest_api_init` automatically.
Default permission: `is_user_logged_in()` when no `.permission()` is set.

### Http (outbound HTTP client)

**`UupCode\Utilities\Http\Http`** — wraps WordPress HTTP API (`wp_remote_*`).

```php
use UupCode\Utilities\Http\Http;

$res = Http::get('https://api.example.com/items')
    ->withHeader('Authorization', 'Bearer ' . $token)
    ->withQuery(['page' => 2, 'per_page' => 10])
    ->timeout(15)
    ->send();

$res->ok();       // bool — 2xx status
$res->status();   // int
$res->json();     // array — throws RuntimeException if not JSON
$res->body();     // string
$res->header('content-type');

Http::post('https://api.example.com/items')
    ->withJson(['name' => 'Widget', 'price' => 9.99])
    ->send()
    ->throw();    // throws RuntimeException if not 2xx

Http::post($url)->withBody('raw string')->withHeader('Content-Type', 'text/plain')->send();
Http::get($url)->safe()->send();    // uses wp_safe_remote_* (blocks private IPs)
Http::get($url)->sslVerify(false)->send();
```

---

## Logging

**`UupCode\Utilities\Logging\Log`** — multi-channel PSR-3 logger.

```php
use UupCode\Utilities\Logging\Log;

// Zero-config (writes to error_log / WP_DEBUG_LOG by default)
Log::info('User logged in', ['user_id' => 5]);
Log::error('Payment failed', ['order_id' => 99]);
Log::debug('Query ran', ['sql' => $sql]);
// All levels: debug, info, notice, warning, error, critical, alert, emergency

// Configure named channels once during plugin boot
Log::configure([
    'default'  => 'app',
    'channels' => [
        'app' => [
            'driver' => 'single',
            'path'   => WP_CONTENT_DIR . '/logs/myplugin.log',
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

// Named channel
Log::channel('payments')->error('Stripe failed', ['payload' => $data]);
```

**Drivers:** `errorlog` (default), `single` (one file), `daily` (date-stamped + pruning).

---

## Cache

**`UupCode\Utilities\Cache`** — WordPress Object Cache facade.

```php
use UupCode\Utilities\Cache;

Cache::get('key', 'group', $default);
Cache::set('key', $value, 'group', 3600);
Cache::delete('key', 'group');
Cache::has('key', 'group');

// Get-or-compute
Cache::remember('posts', 'myplugin', 3600, fn() => DB::table('posts')->get());

// Grouped interface (avoids repeating group name)
$c = Cache::group('myplugin');
$c->get('posts');
$c->set('posts', $data, 600);
$c->remember('posts', 600, fn() => fetch_posts());
$c->flush(); // requires persistent object cache (Redis etc.)

// Batch
Cache::getMany(['key1', 'key2'], 'group');
Cache::setMany(['key1' => $v1, 'key2' => $v2], 'group', 600);
Cache::deleteMany(['key1', 'key2'], 'group');
```

---

## Option

**`UupCode\Utilities\Option`** — `get_option` / `update_option` facade (check source for API).

---

## Meta

**`UupCode\Utilities\Meta`** — unified post/user/term/comment meta facade.

```php
use UupCode\Utilities\Meta;

Meta::post(5)->get('_price');
Meta::post(5)->set('_price', 99.99);
Meta::post(5)->has('_price');
Meta::post(5)->delete('_price');
Meta::post(5)->all('_tag');         // all values for key
Meta::post(5)->every();             // all meta as array<key, list<value>>
Meta::post(5)->setMany(['_price' => 99, '_sku' => 'ABC']);

Meta::user($userId)->get('_billing_country');
Meta::term($termId)->set('_icon', 'star');
Meta::comment($commentId)->get('_rating');
```

---

## PostTypes

**`UupCode\Utilities\PostTypes\PostType`** / **`Taxonomy`** — fluent CPT/taxonomy builders.

```php
use UupCode\Utilities\PostTypes\PostType;
use UupCode\Utilities\PostTypes\Taxonomy;

PostType::make('product', singular: 'Product', plural: 'Products')
    ->supports('title', 'editor', 'thumbnail', 'excerpt')
    ->public()
    ->rewrite('products')
    ->menuIcon('dashicons-cart')
    ->hasArchive()
    ->showInRest()
    ->register();

Taxonomy::make('product_cat', postTypes: 'product', singular: 'Category', plural: 'Categories')
    ->hierarchical()
    ->rewrite('product-category')
    ->showAdminColumn()
    ->register();

// Retrieve a registered instance later
PostType::get('product');
Taxonomy::get('product_cat');
```

Registration is deferred to `init` hook automatically.
Register these in `PostTypeServiceProvider::register()` (override, not attribute-based).

---

## Cron

**`UupCode\Utilities\Cron`** — WP-Cron helpers.

```php
use UupCode\Utilities\Cron;

Cron::add('myplugin_sync', 'hourly', fn() => sync_data());
Cron::add('myplugin_cleanup', 'daily', [CleanupJob::class, 'run']);
Cron::remove('myplugin_sync');
Cron::isScheduled('myplugin_sync');  // bool
Cron::nextRun('myplugin_sync');      // ?int timestamp

// Custom interval
Cron::addInterval('every_5_minutes', 300, 'Every 5 Minutes');
```

Register in `CronServiceProvider::register()` (override, not attribute-based).
Unschedule all events in `Plugin::deactivate()`.

---

## Shortcode

**`UupCode\Utilities\Shortcode`** — shortcode helpers.

```php
use UupCode\Utilities\Shortcode;

Shortcode::register('my_button', function(array $atts, string $content = ''): string {
    $atts = shortcode_atts(['label' => 'Click', 'url' => '#'], $atts);
    return sprintf('<a href="%s">%s</a>', esc_url($atts['url']), esc_html($atts['label']));
});

Shortcode::remove('embed');
Shortcode::exists('my_button');
```

Register in `ShortcodeServiceProvider` using `#[Action('init')]` attribute.

---

## Notice

**`UupCode\Utilities\Notice`** — admin notice builder.

```php
use UupCode\Utilities\Notice;

Notice::success('Settings saved.')->show();
Notice::error('API key invalid.')->dismissible()->show();
Notice::warning('Cache is stale.')->onPage('plugins.php')->show();
Notice::info('Update available.')->dismissible()->show();
```

---

## Key Patterns

### Provider that uses attribute-based hooks
```php
use UupCode\Utilities\ServiceProvider;
use UupCode\Utilities\Attributes\Action;
use UupCode\Utilities\Attributes\Filter;

final class MyProvider extends ServiceProvider {
    #[Action('init')]
    public function onInit(): void { ... }

    #[Action('wp_enqueue_scripts')]
    public function enqueueAssets(): void { ... }

    #[Filter('body_class', args: 2)]
    public function addBodyClass(array $classes, array $cssClass): array {
        return [...$classes, 'my-class'];
    }
}
```

### Provider that overrides register() directly
Use this for Ajax, PostType, Cron, Shortcode — classes that manage their own hook registration:

```php
final class AjaxServiceProvider extends ServiceProvider {
    public function register(): void {
        Ajax::handle('myplugin_save', function(SaveRequest $request) {
            Ajax::json(['ok' => true]);
        })->register();
    }
}
```

### Complete AssetServiceProvider pattern
```php
final class AssetServiceProvider extends ServiceProvider {
    #[Action('wp_enqueue_scripts')]
    public function enqueueFrontend(): void {
        $asset = require Plugin::path('build/index.asset.php');
        Asset::script('myplugin', Plugin::url('build/index.js'))
            ->deps(...$asset['dependencies'])
            ->version($asset['version'])
            ->footer()
            ->localize('myplugin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('myplugin'),
            ])
            ->enqueue();
        Asset::style('myplugin', Plugin::url('build/index.css'))
            ->version($asset['version'])
            ->enqueue();
    }

    #[Action('admin_enqueue_scripts')]
    public function enqueueAdmin(): void {
        $asset = require Plugin::path('build/admin.asset.php');
        Asset::script('myplugin-admin', Plugin::url('build/admin.js'))
            ->deps(...$asset['dependencies'])
            ->version($asset['version'])
            ->footer()
            ->enqueue();
    }
}
```

### Model + Cache pattern
```php
class OrderModel extends Model {
    protected static string $table = 'myplugin_orders';
    protected static array $casts  = ['meta' => 'array', 'amount' => 'float'];

    public static function recentPaid(int $limit = 10): array {
        return Cache::remember('recent_paid', 'myplugin', 300, fn() =>
            static::hydrate(
                static::query()
                    ->where('status', 'paid')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->get()
            )
        );
    }
}
```
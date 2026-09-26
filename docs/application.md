# Application setup

This is a guide to the current API. For signatures and defaults, start with [App](../src/App.php), [Config](../src/Config.php), and [Config\Defaults](../src/Config/Defaults.php).

## Configuration

Pass the project root and a settings array to `App::create()`. The app exposes the resulting immutable configuration as `$app->config`; construct settings before boot because routes and services consume them during setup. `Config::with()` returns a changed copy, not a mutation of the running app.

```php
use Cosray\App;

$root = dirname(__DIR__);
$app = App::create($root, [
    'app.name' => 'mycms',
    'panel.path' => '/cp',
    'db.sql' => ["{$root}/db/sql"],
]);
$app->config->requireEnv(['DATABASE_URL', 'APP_SECRET']);

$panel = $app->config->panel->path;
$name = $app->config->get('app.name');
```

Recognized environment variables are read from `$_SERVER`, then `$_ENV`, not `getenv()`. Cosray does **not** load `.env` files. If an application uses a loader such as `vlucas/phpdotenv`, run it before constructing the app and let it populate those arrays. Boolean and integer environment settings are validated at construction; malformed values and missing `requireEnv()` variables throw `Cosray\Exception\InvalidEnvironment`. Use native booleans and integers in PHP settings.

The complete settings list lives in [Defaults.php](../src/Config/Defaults.php); conversions and validation live in [Config/](../src/Config/). `session.enabled` controls frontend session middleware. The panel has its own session handling. `app.timezone` controls panel timestamp display.

### Filesystem directories and URL paths

| Setting | Meaning |
| --- | --- |
| `path.root` | Project filesystem root supplied to `App::create()` |
| `path.public` | Filesystem document root; defaults to `$root/public` |
| `path.views` | View directory relative to `path.root`; `/views` means `$root/views` |
| `path.assets` | Originals below `path.public`, also their URL path; defaults to `/assets` |
| `path.cache` | Renditions below `path.public`, also their URL path; defaults to `/cache` |
| `app.url_prefix` | Application URL mount prefix; does not change filesystem locations |
| `panel.path` | Panel URL path; defaults to `/cp` |

`path.assets` and `path.cache` couple directories below the document root to media URL paths. With `app.url_prefix => '/site'`, an original below `$root/public/assets` has a URL starting with `/site/assets/`. The router applies the prefix to routes, but panel-generated links and login redirects do not consistently include non-empty prefixes yet.

## Views and errors

Cosray bundles Boiler as the default `view` renderer, reading from `{path.root}{path.views}`. To replace it or pass renderer arguments, register it before boot:

```php
use Cosray\View\Boiler\Renderer;

$app->renderer('view', Renderer::class)->args(
    dirs: __DIR__ . '/custom-views',
    defaults: ['siteName' => 'My Site'],
);
```

Error pages use a separate Boiler renderer. Project `http-error.php` and `http-server-error.php` templates override the built-in fallbacks. Set `error.enabled` to `false` when installing custom error middleware. The lower-level core app and CMS bootstrap remain accessible through `$app->core()` and `$app->bootstrap()`.

## Console commands

[Cosray\Console\Commands](../src/Console/Commands.php) registers the migration commands, index rebuilds, and superuser command. `php run help` lists the commands actually registered by the application; app-specific commands resolve lazily.

A project `run` script can load an `app/console.php` returning the runner:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

exit((require __DIR__ . '/app/console.php')->run());
```

```php
<?php

declare(strict_types=1);

use Cosray\Console\Commands;

$app = require __DIR__ . '/app.php';
$commands = new Commands($app);
$commands->server(port: 6913, watch: ['src/**/*.php', 'views/**/*.php']);
$commands->i18n('mysite', locales: ['de', 'en'], scan: ['src', 'views']);
$commands->add(\App\Command\Import::class);

return $commands->runner();
```

- `server()` registers the built-in PHP server and FrankenPHP when the optional `celema/server` package is installed. FrankenPHP additionally needs its executable on `PATH` and uses its embedded PHP runtime.
- `i18n()` registers synchronization and status commands for a translation domain, scanning source paths and schema labels. Call it per domain when needed.
- `add()` accepts instances, class names, or keyed factories for commands needing custom scalar arguments.

Commands can inject `Cms`, `Context`, `Config`, `Database`, `Locales`, and `Cosray\Node\Writer`. They run with the default content locale and a Verba translator but without an HTTP request or session. Use `Context::withLocale()` for locale-specific work.

### Creating content

```php
use Cosray\Actor;

$prepared = $writer
    ->prepare(\App\Node\Article::class, ['title' => 'New article'])
    ->uid('article-stable-id')
    ->published();

$writer->create($prepared, new Actor($editorId));
```

[Writer](../src/Node/Writer.php) coordinates field blueprints and persistence; [Prepared](../src/Node/Prepared.php) exposes node settings, paths, and field metadata. Omitting the actor records the seeded system user. An explicit path bypasses generation and rejects collisions or unknown locales instead of silently suffixing a URL an importer intended to preserve.

The lower-level [Node\Store](../src/Node/Store.php) takes `Locales` and an explicit `Cosray\Actor`; HTTP callers derive the actor from their authenticated session. See [working copies](content.md#working-copies) before choosing a store operation.

## Plugins

Runtime plugins implement [Cosray\Plugin\Plugin](../src/Plugin/Plugin.php) and register through [Registrar](../src/Plugin/Registrar.php):

```php
use Cosray\Plugin\Plugin;
use Cosray\Plugin\Registrar;

final class ShopPlugin implements Plugin
{
    public function id(): string
    {
        return 'acme-shop';
    }

    public function register(Registrar $cms): void
    {
        $cms->node(\App\Node\Product::class);
        $cms->section('Shop')->collection(\App\Collection\Products::class);
        $cms->migrations(__DIR__ . '/../db/migrations');
        $cms->sql(__DIR__ . '/../db/sql');
    }
}

$app->plugin(ShopPlugin::class);
```

The `plugins` config key also accepts plugin registrations. Classes registered by name are constructed without arguments; register a pre-built instance when construction needs arguments. IDs currently allow lowercase letters, digits, and dashes and require a dash, keeping plugin config namespaces separate from built-in settings.

Settings use flat namespaced keys, such as `'acme-shop.currency' => 'USD'`. Within registration, `$cms->option('currency', 'EUR')` reads that key with a default; elsewhere, use `Config`. Custom field classes extend `Cosray\Field\Field`; `Registrar::field()` string aliases are for legacy imports, not required when node properties use the field class directly.

Plugin migrations share the `default` namespace. Timestamped filenames avoid collisions; use Quma's `/*:cms.prefix:*/` placeholder for configured table prefixes and bound parameters for runtime data.

### Panel pages and navigation

```php
$panel = $cms->config->panel->path;
$cms->templates(__DIR__ . '/../views');
$cms->panelPage('/shop/orders', [\App\Panel\Orders::class, 'list'], 'acme-shop:orders', 'orders');
$cms->section('Shop')->link('Orders', "{$panel}/shop/orders");
$cms->assets(__DIR__ . '/../dist');
$cms->css("{$panel}/vendor/acme-shop/shop.css");
$cms->js("{$panel}/vendor/acme-shop/shop.js");
```

Panel controllers extend [Panel](../src/Controller/Panel/Panel.php) and return `$this->context([...])`. Their templates call `$this->layout('layer/main')` and render only their fragment, without another `id="main"`. The [layer templates](../panel/views/layer/) wrap it for the requested swap:

- `main`: content inside an area.
- `frame`: rail and content on an area switch.
- `shell`: panel chrome for history restore or a full htmx render, without scripts.
- `document`: a complete page for an ordinary request.

Existing nodes open at `{panel.path}/node/{uid}`; creation uses `{panel.path}/node/create/{type}`. Build URLs through [NodeUrls](../src/Panel/NodeUrls.php):

```php
$links = new \Cosray\Panel\NodeUrls($config->panel->path);
$editUrl = $links->edit($uid);
$createUrl = $links->create('article', parent: $parentUid);
```

Optional `from=collection:{handle}` or `from=dashboard` supplies return navigation, not authorization. Collection list state lives under `list[...]`; creation's top-level `parent` is independent of `list[parent]`. Links switching panel areas target `#frame`.

These endpoints require panel access. Creation accepts registered types, with an explicit parent checked against `#[Children(...)]`. Collection blueprints restrict displayed choices, not endpoint access; a separate node permission policy is not enforced.

### Client lifecycle

Registered scripts load once per full document and do not run again after htmx navigation. Delegated, idempotent listeners or custom elements work across replaced fragments; load-time DOM decoration alone does not. Panel markup is internal unless an extension boundary is documented. See [editor controls](controls.md) for the custom-element and bridge interfaces.

### Dashboard cards

`$app->dashboard->add(Provider::class)` appends a provider; `replace(...)` selects and orders the complete list. Plugins append through `Registrar::dashboardCard()`. Providers implement [DashboardCard](../src/Contract/DashboardCard.php) and return a nullable [Card](../src/Panel/Dashboard/Card.php). Class names are autowired per dashboard request; `null` omits the card. Labels and notes are text, integer values are locale-formatted, strings are display-ready, and optional URLs are panel-internal.

`panel.dashboard => false` removes the dashboard entry and directs panel home to the first collection, or media when there is none.

## Panel assets and theming

The panel's browser files ship with the package as they are, so `composer install` or `update` is the whole installation. PHP serves `panel/src`, `panel/styles`, `panel/icons` and the vendored third-party modules in `panel/modules` under `{panel.path}/assets/{revision}/`, plus an icon sprite it builds from `panel/icons`. The revision is the start of the commit Composer installed, or a hash of the version for a package without one. Responses for the installed revision may be cached for a year (`immutable`); in debug mode, in a Git working copy such as a symlinked path repository, and for any other revision they are revalidated through an ETag.

A first visit loads the modules one by one: about 90 files for the dashboard and 130 for an editor with rich text and code, around 200 and 700 KB compressed. Make sure the web server compresses `text/javascript` and `text/css` responses (gzip, Brotli or zstd) and speaks HTTP/2; uncompressed, the same visits move several times the bytes. Later visits load nothing but the page.

Letting the web server deliver these files instead of PHP is optional. A mapping has to drop the revision segment, allow only `src/`, `styles/`, `modules/` and `icons/` with the `.js`, `.css` and `.svg` extensions, serve `.js` as `text/javascript`, and send the immutable `Cache-Control` header only for the installed revision; the sprite `icons.svg` has to reach PHP.

`panel.theme` accepts a stylesheet URL or a list of URLs. [Panel styles](panel-styles.md) explains the cascade and current theming approach. [Panel development](../panel/README.md) covers the optional Vite dev server and the live styleguide.

## Users, roles and permissions

Roles and allow entries live in code; the database stores each user's role names. [Security\Policy](../src/Security/Policy.php) resolves principals (`everyone`, `authenticated`, `user:{uid}`, `type:{handle}`, `role:{name}`) against permissions. Grants from multiple roles combine; unknown roles grant nothing.

```php
$app->role('shop', 'Shop manager');
$app->allow('role:shop', 'panel', 'edit-orders');
$policy->permits($user, 'edit-orders');
```

Routes use `#[Permission(...)]` middleware; panel controllers can call `$this->permits(...)`. The Users area needs `edit-users`. Its write policy prevents editors from granting access beyond their own, changing their own roles/state, or removing the last active superuser. Password changes end other sessions and remembered logins. The profile page allows self-service account changes, with the current password required for a new one.

Users have an email login and optional username without `@`. A subclass of [Cosray\User](../src/User.php), registered through `$app->user()`, adds fields like a node class. `#[Roles(...)]` limits assignable roles; `#[Roles]` allows none. Fields are neutral-locale values stored under `content` in `users.data`, without working copies, and hydrate on demand through [User\Fields](../src/User/Fields.php). See [User\Types](../src/User/Types.php) and its tests for type registration and default-type replacement.

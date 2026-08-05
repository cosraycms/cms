# Cosray CMS

<!-- prettier-ignore-start -->
[![ci](https://codeberg.org/cosray/cms/badges/workflows/ci.yml/badge.svg?style=flat&logo=codeberg&logoColor=white&label=ci)](https://codeberg.org/cosray/cms/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcosray%2Fcms%2Fcode%2Fbadge.json)](https://cov.celema.dev/cosray/cms/code)
[![REUSE status](https://api.reuse.software/badge/codeberg.org/cosray/cms)](https://api.reuse.software/info/codeberg.org/cosray/cms)
[![License](https://img.shields.io/badge/license-MPL--2.0-blue)](LICENSES/MPL-2.0.txt)
[![Panel License](https://img.shields.io/badge/panel_license-MIT-blue)](LICENSES/MIT.txt)

> [!WARNING]
> _Thanks for stopping by! This project is in an early, fast-moving stage. The API and data model are still unstable, and documentation is minimal or missing. I'm aware of many of the rough edges, so contributions are probably not worth your time right now._
<!-- prettier-ignore-end -->

**Cosray CMS is a PHP content management framework for building structured websites with code-first content models, PostgreSQL-backed storage, and an admin panel for editors.**

## Bootstrapping

Use `Cosray\App` for regular CMS applications. It creates the config, core app, and CMS bootstrap internally, installs the default error handler, adds CMS routes, and registers the catchall route when you call `run()`.

```php
use Cosray\App;
use Cosray\Locales;

$app = App::create(dirname(__DIR__), [
    'app.name' => 'mycms',
    'session.enabled' => true,
]);

$locales = new Locales();
$locales->add('en', title: 'English', pgDict: 'english');
$app->load($locales);

$app->section('Content')->collection(\App\Cms\Collection\Pages::class);
$app->node(\App\Cms\Node\HomePage::class);

$app->run();
```

The CMS app exposes the common CMS configuration API (`section()`, `collection()`, `node()`, `renderer()`, `icons()`) and the common core app API (`load()`, `middleware()`, `get()`, `post()`, `routes()`, `run()`). Use `core()` or `bootstrap()` only when you need the lower-level APIs directly.

## Console commands

`Cosray\Console\Commands` boots the configured `App` and bundles its base CLI command set: the quma migration commands (`db:add-migration`, `db:create-migrations-table`, `db:migrations`), Cosray's own `db:fulltext`, `db:references`, `db:recreate-sort-index`, `db:titles`, `panel:install`, and `add-superuser`. Commands are registered as lazy factories — nothing is constructed until a command actually runs, so `php run help` stays free of database and app-context setup.

The recommended layout is a two-line `run` script:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

exit((require __DIR__ . '/app/console.php')->run());
```

and an `app/console.php` returning the runner:

```php
<?php

declare(strict_types=1);

use Cosray\Console\Commands;

$app = require __DIR__ . '/app.php'; // returns the configured Cosray\App

$commands = new Commands($app);
$commands->server(port: 6913, watch: ['src/**/*.php', 'views/**/*.php']);
$commands->i18n('mysite', locales: ['de', 'en'], scan: ['src', 'views']);

// App-specific commands are lazily autowired from the CMS console scope.
$commands->add(ImportCommand::class);

// Explicit factories remain available for scalar or unusual arguments.
$commands->add([ExportCommand::class => fn() => new ExportCommand($target)]);

return $commands->runner();
```

- **`server(port:, watch:, routePrefix:)`** registers the PHP built-in server (`php run server`) and FrankenPHP (`php run frankenphp`) on the app's public directory.
- **`i18n(name, locales:, scan:, dir:, schema:)`** registers `i18n:sync` and `i18n:status` for one translation domain. It scans the given source directories (relative paths resolve from the app root) plus the app's schema labels, and claims bare `__()` calls as the default domain. Call it once per domain for apps with several catalogs.
- **`add()`** accepts instances, class-strings, and lazy factories keyed by class-string. Class-strings are lazily autowired from one request-free console scope, so commands can inject `App`, `Cms`, `Context`, `Config`, `Database`, `Locales`, `Types`, and `Cosray\Node\Writer` directly. Explicit factories remain available when scalar arguments need application-specific configuration.
- **`runner(debug:)`** returns the ready `Celema\Console\Runner`; `debug` defaults to the app config's `debug()`.

The `frankenphp` command requires a `frankenphp` executable in `PATH` and runs it in classic mode. It supports the same configured port and BrowserSync-backed `--watch` patterns as the built-in server. Its `--debug` option enables verbose Caddy logs; FrankenPHP uses its embedded PHP runtime rather than the PHP CLI that starts `run`.

Console commands run with the default content locale and an active Verba translator, but without an HTTP request or session. A command that injects `Celema\Core\Request` therefore fails at resolution instead of receiving a synthetic request. Use `Context::withLocale()` for a locale-specific callback; Cosray's title materialization and node writer do this automatically where needed.

### Creating content from commands

`Cosray\Node\Writer` hides node factory, blueprint serializer, and store coordination. Build a draft from field values, apply node-level settings, then create it. The default actor is the seeded system user; pass an explicit `Cosray\Node\Actor` when another audit identity owns the change.

```php
use Cosray\Node\Actor;
use Cosray\Node\Writer;

$draft = $writer
    ->draft(Alert::class, [
        'value' => '31.4',
        'readingTime' => '2026-08-05 12:00:00',
    ])
    ->uid('alert-stable-id')
    ->published()
    ->fieldMeta('readingTime', 'timezone', ['zxx' => 'UTC']);

$writer->create($draft, new Actor($editorId));
```

The lower-level `Node\Store` is also request-neutral: callers pass `Locales` and an explicit `Actor`; HTTP controllers remain responsible for deriving that actor from their authenticated session.

## Plugins

Runtime plugins are Composer packages (or project classes) implementing `Cosray\Plugin\Plugin`. They are registered explicitly — either in the bootstrap or through the `plugins` config key:

```php
$app->plugin(\Acme\Shop\ShopPlugin::class);

// or in the settings array:
'plugins' => [\Acme\Shop\ShopPlugin::class],
```

A plugin declares a stable id and registers everything through the `Registrar`:

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
        $cms->field(Field\Money::class, 'money');
        $cms->control('acme-map', 'acme-map-picker', 'map.js');
        $cms->node(Node\Product::class);
        $cms->section('Shop')->collection(Collection\Products::class);
        $cms->migrations(__DIR__ . '/../db/migrations');
        $cms->sql(__DIR__ . '/../db/sql');
        $cms->register('acme-shop.gateway', PaymentGateway::class);
        $cms->routes(static function ($app): void {
            $app->get('/shop/checkout', [Controller\Checkout::class, 'show'], 'acme-shop.checkout');
        });
    }
}
```

Plugins must be constructible without arguments. Custom field types are plain `Cosray\Field\Field` subclasses referenced by class on node properties; string aliases passed to `field()` are only needed for legacy content imports. Plugin migrations run in the shared `default` migration namespace: use timestamped filenames and the `/*:cms.prefix:*/` placeholder in table names (for example `/*:cms.prefix:*/acmeshop_orders`).

### Panel apps

Plugins can ship whole apps that live inside the panel chrome — session, authentication, sidebar and layout come for free:

```php
public function register(Registrar $cms): void
{
    $panel = $cms->config->panel->path;

    $cms->templates(__DIR__ . '/../views');               // namespace 'acme-shop:'
    $cms->panelPage('/shop/orders', [Controller\Orders::class, 'list'], 'acme-shop:orders', 'orders');
    $cms->section('Shop')->link('Bestellungen', "{$panel}/shop/orders");
    $cms->assets(__DIR__ . '/../dist');                   // {panel}/vendor/acme-shop/...
    $cms->css("{$panel}/vendor/acme-shop/shop.css");
    $cms->js("{$panel}/vendor/acme-shop/shop.js");
}
```

Panel page controllers extend `Cosray\Controller\Panel\Panel` and return `$this->context([...])`; the page template calls `$this->layout('panel')` to render inside the shell. Custom editor UIs (field controls, block types) ship as web components and use the `window.Cosray` runtime (modals, uploads, toasts, system info) — see `docs/controls.md` for the control vocabulary and the element contract. Cosray's own rich controls (richtext, code, media, blocks, entries) are built the same way and serve as reference implementations under `panel/src/elements/`.

## Defining content types

Content types (nodes) are plain PHP classes annotated with attributes. There is no base class to extend. Dependencies are autowired from the Registry via `celema/wire`.

```php
use Celema\Core\Request;
use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Field\Blocks;
use Cosray\Field\Image;
use Cosray\Cms;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;

#[Label('Department'), Route('/{title}')]
final class Department implements Title
{
    public function __construct(
        protected readonly Request $request,
        protected readonly Cms $cms,
    ) {}

    #[Label('Title'), Required, Translate]
    public Text $title;

    #[Label('Content'), Translate(TranslateMode::Asymmetric)]
    public Blocks $content;

    #[Label('Image')]
    public Image $clipart;

    public function title(): string
    {
        return $this->title?->value()->unwrap() ?? '';
    }
}
```

### Embedded fields and fieldsets

Reusable field declarations live in classes implementing `Cosray\Contract\Embedded`. The node owns a real embedded object, so it can call public behavior on that object, while Cosray keeps the child fields flat in content, forms, routes, validation, and node-proxy access.

```php
use Cosray\Contract\Embedded;
use Cosray\Contract\Title;
use Cosray\Field\Blocks;
use Cosray\Field\Text;
use Cosray\Schema\Description;
use Cosray\Schema\Fieldset;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Schema\Width;

#[Label('Base fields')]
final class BaseFields implements Embedded, Title
{
    #[Label('Title'), Required, Translate]
    protected Text $title;

    #[Label('Content')]
    protected Blocks $content;

    public function title(): string
    {
        return $this->title->value()->unwrap() ?? '';
    }
}

final class Article implements Title
{
    #[Fieldset, Label('Content'), Description('General article content'), Width(50)]
    protected BaseFields $baseFields;

    public function title(): string
    {
        return $this->baseFields->title();
    }
}
```

The stored keys are `title` and `content`, never `baseFields.title`. They remain available as `$node->title` and in route placeholders such as `{title}`. The embedded object is available as `$node->baseFields` for its public methods.

Embedding properties determine placement order; child declaration order determines their order within that placement. Without `#[Fieldset]`, children render as ordinary fields. `#[Fieldset]` groups them in the panel; `Label`, `Description`, and `Width` configure that group, and omitting `Label` produces a label-less fieldset.

Embedded constructors are autowired with the node's services. Fields are assigned afterwards; implement `Cosray\Contract\Init` for field-dependent initialization. Embedded types are transient and cannot inject their containing node or be registered as shared services. Field names must be unique across the whole node, and recursive embeds are not supported.

An outer `Title` implementation takes precedence. Otherwise, `#[Title]` can select an embedded title provider, one embedded `Title` provider is detected automatically, and ordinary explicit or `title` text fields remain supported. Multiple automatic providers are rejected as ambiguous.

`Entries` schemas reuse embedded field declarations and fieldset layout, but they are static value schemas: they do not instantiate embedded objects or run their constructors, methods, or initialization hooks.

### Field translation modes

`#[Translate]` defaults to symmetric translation. Symmetric media fields share one file list and translate metadata such as `title` and `alt`.

Use `#[Translate(TranslateMode::Asymmetric)]` when the whole field payload varies by locale. `Blocks` currently supports asymmetric translation only. Media fields use asymmetric translation for separate per-locale file lists. Required asymmetric fields require content in the default locale; fallback locales are optional.

### Derived behavior

| Signal                        | Behavior                                   |
| ----------------------------- | ------------------------------------------ |
| `#[Route('...')]` is present  | Node is routable and has URL path settings |
| `#[Render('...')]` is present | Explicit renderer id is used               |
| `#[Render]` is absent         | Node handle is used as renderer id         |

### Metadata attributes

| Attribute | Purpose |
| --- | --- |
| `#[Label('...')]` | Human-readable display name |
| `#[Handle('...')]` | URL-safe identifier (auto-derived if omitted) |
| `#[Route('...')]` | Route template for routable nodes |
| `#[Render('...')]` | Template name override |
| `#[Title('...')]` | Field name to use as title |
| `#[FieldOrder('...')]` | Admin panel field order |
| `#[Fieldset]` | Group an embedded property's fields in the panel |
| `#[Deletable(false)]` | Prevent deletion in admin panel (default: `true`) |
| `#[Children(Foo::class, ...)]` | Allowed direct child node types for hierarchy-enabled collection lists |

Route templates can generate URL paths from node fields and hierarchy data.

## Collections

Collections are configured through class attributes — the same schema mechanism as nodes and fields. Behavior (the query, columns, sorts) stays on methods:

```php
use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Icon;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;

#[Label('Aktuelles'), Handle('aktuelles'), Icon('bi:newspaper'), Listing(children: true)]
final class News extends Collection
{
    public function entries(): Nodes
    {
        return $this->cms->nodes()->types('news')->published(null);
    }
}
```

Available attributes: `#[Label]`, `#[Handle]`, `#[Icon]`, `#[Badge]`, `#[Permission]`, `#[Hidden]`, `#[Order]`, `#[Listing(published:, locked:, hidden:, children:)]`, `#[Blueprints(...)]`. Handle and label derive from the class name when omitted. Plugins can register additional collection schema attributes via `Registrar::collectionSchema()`.

### Hierarchy lists in panel

- Use `#[Listing(children: true)]` on a collection to switch its panel list to hierarchy mode.
- The collection view renders nodes with no parent as roots; rows with children get tree controls that expand direct children.
- Child create options are derived from `#[Children(...)]` declarations.

### Behavioral interfaces

All of them live in `Cosray\Contract`.

| Interface | Method | Applies to | Purpose |
| --- | --- | --- | --- |
| `Embedded` | — | embedded classes | Reusable class whose fields are flattened into its owner |
| `Title` | `title(): string` | nodes, embedded | Computed title provider (takes precedence over implicit fields) |
| `Init` | `init(): void` | nodes, embedded | Post-hydration initialization hook |
| `HttpGet` | `httpGet(): Response` | nodes | Answers GET on the node's public path, replacing the default render |
| `HttpPost` | `httpPost(): Response` | nodes | Answers POST (form submissions) |
| `HttpPut` | `httpPut(): Response` | nodes | Answers PUT |
| `HttpDelete` | `httpDelete(): Response` | nodes | Answers DELETE |
| `ViewContext` | `viewContext(Wrapper $node): array` | nodes | Extra template variables |

The `Http*` interfaces answer requests to the node's own public path — the frontend catchall, and for GET the panel preview route as well. A node implementing one takes over that method entirely: Cosray dispatches to it before its own handling, so the node also owns content negotiation for that request. Methods without a hook keep the defaults: GET renders the node's view (or returns its JSON for `Accept: application/json`), everything else answers `400`.

They take no arguments — read the submitted body through the autowired `Celema\Core\Request`. PHP only fills the parsed body for form-encoded POST, so use `Cosray\Util\Form::body()` when a PUT or a JSON payload has to be read:

```php
use Cosray\Contract\HttpPost;
use Cosray\Util\Form;

class ContactPage implements HttpPost
{
    public function __construct(
        private readonly Request $request,
        private readonly Factory $factory,
    ) {}

    public function httpPost(): Response
    {
        $body = Form::body($this->request);

        return $this->view->render(['error' => $this->validate($body)]);
    }
}
```

### Rendering a node's own view

Nodes are autowired with `Cosray\Node\View`, bound to the node itself. It renders the node's template and returns the response, so a handler can answer with the node itself plus a message or the submitted values:

```php
public function __construct(
    private readonly Request $request,
    private readonly View $view,
) {}

public function httpPost(): Response
{
    return $this->view->render(['error' => __('Please confirm.')]);
}
```

`render()` returns a `Response`; `output()` returns the rendered template as a string, for nodes that build their own response. The context passed here wins over `ViewContext::viewContext()`, so a node declares defaults in the hook and overrides them per request at the call site.

Templates receive the node as `$node`, whether it is served at its own url path or embedded through `$cms->render()`. The wrapper is fully equipped: `$node->children()`, `path()` and `meta` work the same as on a node fetched through `$cms->node`.

### Rendering by handle or UID

Render a node by handle from templates with the neutral CMS API:

```php
<?= $cms->render('downloads') ?>
```

`render()` resolves handles first and falls back to immutable UIDs.

## Boiler rendering

`cosray/cms` bundles the Boiler renderer under the `Cosray\View\Boiler` namespace and registers it as the default `view` renderer. You do not need to require a separate renderer package or register a renderer for the common case.

By default, views are loaded from `{path.root}{path.views}`. `path.root` is the project root passed to `App::create()`. `path.views` defaults to `/views` and can be overridden in CMS config:

```php
use Cosray\App;

$app = App::create(dirname(__DIR__), [
    'path.views' => '/views',
]);
```

To replace the default renderer or pass custom Boiler arguments, register a `view` renderer before the app boots:

```php
use Cosray\App;
use Cosray\View\Boiler\Renderer;

$app = App::create(dirname(__DIR__), [
    'app.name' => 'mycms',
]);
$app->renderer('view', Renderer::class)->args(
    dirs: __DIR__ . '/custom-views',
    defaults: ['siteName' => 'My Site'],
);
```

`Cosray\App` installs the bundled error handler by default. Error pages use a dedicated Boiler renderer, so replacing the CMS `view` renderer does not affect error rendering. Project templates named `http-error.php` and `http-server-error.php` in `{path.root}{path.views}` override the built-in fallback templates. Set `error.enabled` to `false` if you want to call `$app->core()->errorHandler(...)` yourself or handle errors in custom middleware.

For advanced integrations, the bundled error integration remains available as `Cosray\View\Boiler\Error\Handler`. Pass a `Cosray\Config`, core factory, and logger when you create it manually.

## Settings

`App::create()` creates `Config` from the root path and settings array and exposes it as `$app->config`. `Config` loads `.env` from the root path with `Dotenv::safeLoad()` and merges built-in defaults with the settings array. Use `requireEnv()` when an application wants to fail fast for required environment variables.

Prefer building the settings array upfront and passing it once to `App::create()` or `new Config(...)`. `Config` is immutable after construction, and values such as `path.prefix`, `path.panel`, and `error.enabled` are consumed while the app boots. The immutable shape also lets typed config objects lazily normalize, validate, and cache values safely across long-running worker processes. Use native booleans and integers in PHP settings; environment values are cast by the built-in defaults.

```php
use Cosray\App;

$root = dirname(__DIR__);
$settings = [
    'app.name' => 'mycms',
    'path.public' => "{$root}/public",
    'path.panel' => '/cp',
    'db.dsn' => env('DATABASE_URL'),
    'db.sql' => ["{$root}/db/sql"],
    'panel.theme' => "{$root}/theme",
];

$app = App::create($root, $settings);
$app->config->requireEnv(['DATABASE_URL', 'APP_SECRET']);
```

Use `$config->with(...)` sparingly when you need a changed standalone config copy, for example in tests or small derived configurations. Avoid long `with()` chains for full application config files; keep the complete settings array easy to scan instead.

Read built-in settings through typed config objects or by key. The built-in objects are `app`, `path`, `panel`, `error`, `icons`, `db`, `session`, `media`, `upload`, and `password`. Their properties convert list-style settings such as `panel.theme`; invalid broad types fail when the relevant property is read.

```php
$name = $app->config->app->name;
$panel = $app->config->panel->path;
$theme = $app->config->panel->theme;
$session = $app->config->session->options;
$timezone = $app->config->app->timezone;

$nameByKey = $app->config->get('app.name');
$debug = $app->config->debug();
$env = $app->config->env();
```

Common built-in settings:

```php
[
    'app.name' => env('APP_NAME', 'cosray'),
    'app.debug' => env('APP_DEBUG', false),
    'app.env' => env('APP_ENV', ''),
    'app.secret' => env('APP_SECRET', null),
    'app.timezone' => env('APP_TIMEZONE', 'UTC'),

    'path.root' => $root,
    'path.public' => $root . '/public',
    'path.prefix' => '',
    'path.assets' => '/assets',
    'path.cache' => '/cache',
    'path.views' => '/views',
    'path.panel' => '/cp',
    'path.api' => null,

    'panel.theme' => [],
    'panel.logo' => '/images/logo.png',

    'db.dsn' => env('DATABASE_URL', null),
    'db.sql' => [],
    'db.migrations' => [],
    'db.print' => false,
    'db.options' => [],

    'session.enabled' => env('SITE_SESSION_ENABLED', false),
    'session.options' => [
        'cookie_httponly' => true,
        'cookie_secure' => env('SESSION_COOKIE_SECURE', true),
        'cookie_lifetime' => (int) env('SESSION_COOKIE_LIFETIME', 0),
        'gc_maxlifetime' => (int) env('SESSION_IDLE_TIMEOUT', 3600),
        'cache_expire' => 3600,
    ],
    'session.handler' => null,

    'error.enabled' => true,
    'error.renderer' => null,
    'error.views' => null,
    'error.whoops' => true,
]
```

The admin panel formats database timestamps with `app.timezone`. Use an IANA identifier such as `Europe/Berlin` for local editor times.

### Admin panel paths

The SSR/HTMX admin panel uses `path.panel`, which defaults to `/cp`.

### Admin panel assets

The panel PHP views ship with the Composer package. The client assets are installed separately from the signed `cosray-panel-{version}.tar.gz` release artifact into `{path.public}{path.panel}/static`. The `Cosray\Console\Commands` facade registers the installer as `panel:install`; run it after Composer installs or updates Cosray, e.g. via the `post-install-cmd`/`post-update-cmd` scripts:

```bash
php run panel:install
```

### Admin panel theming

You can style the admin panel through `panel.theme` in your CMS config. Set it to a single stylesheet path (`string`) or multiple stylesheet paths (`string[]`). The panel links those CSS files in the `theme` cascade layer, so they can override built-in tokens such as `--color-*`, `--space-*`, `--radius-*`, `--font-*`, and `--sidebar-width`.

```php
return [
	'panel.theme' => [
		'/assets/cms/theme/base.css',
		'/assets/cms/theme/brand.css',
	],
];
```

## System requirements

Cosray runs as a PHP application backed by PostgreSQL. Node.js and pnpm are only needed when you develop or rebuild the admin panel assets from source.

### Runtime

- PHP `>=8.5 <9.0`.
- Composer 2 for installing PHP dependencies.
- PostgreSQL 12 or newer. CI uses PostgreSQL 17; use 17 for new projects unless you have verified another version.
- PostgreSQL extensions `btree_gist`, `btree_gin`, and `unaccent`. These are supplied by PostgreSQL contrib packages. The migration role must be allowed to create them, or a database administrator must create them before migrations run.
- PHP extensions required by `composer.json`: `curl`, `dom`, `gd`, `intl`, `pgsql`, `sodium`, and `xml`.
- Standard PHP extensions used by Composer or transitive packages, including `fileinfo`, `iconv`, `json`, `openssl`, `pdo`, `phar`, `simplexml`, and `xmlwriter`.
- A web server or PHP application server that can route requests to the public entrypoint.

Run Composer's platform check after installing dependencies to verify the PHP runtime:

```bash
composer check-platform-reqs
```

### Debian/Ubuntu packages

Install Composer from your distribution package manager or from the [official Composer download page](https://getcomposer.org/download/). When you install it manually, use the verified installer command from that page.

For PHP 8.5 on Debian/Ubuntu, enable a package repository that provides PHP 8.5 packages, such as `deb.sury.org`, then install the runtime packages:

```bash
sudo apt update
sudo apt install -y \
	ca-certificates curl git unzip postgresql-client \
	php8.5-cli php8.5-fpm php8.5-common php8.5-curl \
    php8.5-gd php8.5-intl php8.5-pgsql php8.5-xml
```

Install PostgreSQL server and matching contrib packages on the database host when you host PostgreSQL yourself. Install Xdebug only when you need coverage reports:

```bash
sudo apt install php8.5-xdebug
```

### macOS packages

With Homebrew, install PHP, Composer, and PostgreSQL:

```bash
brew install php composer postgresql@17
```

The Homebrew `php` formula includes the PHP extensions listed above. Run `composer check-platform-reqs` if your shell or PHP-FPM uses another PHP build.

If you use a remote or managed PostgreSQL database, the local PostgreSQL server is optional. Start the Homebrew service when you do use the local server:

```bash
brew services start postgresql@17
```

### Panel development

The SSR/Svelte panel in `panel/` requires Node.js `>=22.13.0` and pnpm `>=11 <12` when you build it from source. Set `COSRAY_PANEL_DEV=1` to load the panel client assets from the Vite dev server instead of the installed static assets. `COSRAY_PANEL_DEV_ORIGIN` overrides the dev server origin; otherwise Cosray uses the current request host and `COSRAY_PANEL_DEV_PORT` (default `2001`).

### Local test database

Test commands default to host `localhost` and database/user/password `cosray`. Override the connection with `COSRAY_DB_HOST`, `COSRAY_DB_NAME`, `COSRAY_DB_USER`, and `COSRAY_DB_PASSWORD` when needed.

```bash
sudo -u postgres createuser --pwprompt --createdb cosray
createdb --user cosray --owner cosray cosray
php ./run db:migrations --namespace=install --apply
php ./run db:migrations --apply
```

## License

Most project files are licensed under [MPL-2.0](LICENSES/MPL-2.0.txt). Files in `panel/` are licensed under [MIT](LICENSES/MIT.txt). See [REUSE.toml](REUSE.toml) for file-level details.

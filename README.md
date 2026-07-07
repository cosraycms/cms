# Cosray CMS

<!-- prettier-ignore-start -->
[![ci](https://codeberg.org/cosray/cms/badges/workflows/ci.yml/badge.svg?style=flat&logo=codeberg&logoColor=white&label=ci)](https://codeberg.org/cosray/cms/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celemas.dev%2Fcosray%2Fcms%2Fcode%2Fbadge.json)](https://cov.celemas.dev/cosray/cms/code)
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

Content types (nodes) are plain PHP classes annotated with attributes. There is no base class to extend. Dependencies are autowired from the Registry via `celemas/wire`.

```php
use Celemas\Core\Request;
use Cosray\Field\Text;
use Cosray\Field\Blocks;
use Cosray\Field\Image;
use Cosray\Cms;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;
use Cosray\Node\Contract\Title;

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

| Interface | Method | Purpose |
| --- | --- | --- |
| `Title` | `title(): string` | Computed title (takes precedence over `#[Title]`) |
| `HasInit` | `init(): void` | Post-hydration initialization hook |
| `HandlesFormPost` | `formPost(?array $body): Response` | Frontend form submission handling |
| `ProvidesRenderContext` | `renderContext(): array` | Extra template variables |

### Rendering by handle or UID

Render a node by handle from templates with the neutral CMS API:

```php
<?= $cms->render('downloads') ?>
```

`render()` resolves handles first and falls back to immutable UIDs.

## Boiler rendering

`cosray/cms` bundles the Boiler renderer under the `Cosray\View\Boiler` namespace and registers it as the default `view` renderer. You do not need to require `celemas/cms-boiler` separately or register a renderer for the common case.

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
    'app.name' => env('APP_NAME', 'celemas'),
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

The panel PHP views ship with the Composer package. The client assets are installed separately from the signed `cosray-panel-{version}.tar.gz` release artifact into `{path.public}{path.panel}/static`. Run the installer after Composer installs or updates Cosray:

```bash
vendor/bin/cosray-panel install
```

Apps that register `Cosray\Commands\InstallPanel` with their own `Config` can also run `php run panel:install`. If the app does not register the installer and uses a non-default panel or public path, pass the paths explicitly:

```bash
vendor/bin/cosray-panel install --panel=/panel --public=public
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

The SSR/Svelte panel in `panel/` requires Node.js `>=20.20.0` and pnpm `>=11 <12` when you build it from source.

### Local test database

Test commands default to host `localhost` and database/user/password `cosray`. Override the connection with `COSRAY_DB_HOST`, `COSRAY_DB_NAME`, `COSRAY_DB_USER`, and `COSRAY_DB_PASSWORD` when needed.

```bash
sudo -u postgres createuser --pwprompt --createdb cosray
createdb --user cosray --owner cosray cosray
php ./run db:migrations --apply
```

## License

Most project files are licensed under [MPL-2.0](LICENSES/MPL-2.0.txt). Files in `panel/` are licensed under [MIT](LICENSES/MIT.txt). See [REUSE.toml](REUSE.toml) for file-level details.

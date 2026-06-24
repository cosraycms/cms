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

Use `Cosray\App` for regular CMS applications. It creates the config, core app, and CMS plugin internally, installs the default error handler, adds CMS routes, and registers the catchall route when you call `run()`.

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

The CMS app exposes the common CMS configuration API (`section()`, `collection()`, `node()`, `renderer()`, `icons()`) and the common core app API (`load()`, `middleware()`, `get()`, `post()`, `routes()`, `run()`). Use `core()` or `plugin()` only when you need the lower-level APIs directly.

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

### Hierarchy lists in panel

- Set `showChildren` to `true` on a collection to switch its list endpoint to hierarchy mode.
- Root requests (`GET /panel/api/collection/{collection}`) return nodes with no parent.
- Child requests (`GET /panel/api/collection/{collection}?parent=<uid>`) return direct children for that parent uid.
- Row payload includes `hasChildren`, `childBlueprints`, and `parent`.
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

### Admin panel paths

The new SSR/HTMX admin panel uses `path.panel`, which defaults to `/cp`. While the legacy SvelteKit panel exists, it is fixed at `/panel`; `install-panel` installs the CI-built legacy panel assets there and does not read `path.panel`.

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

Test database defaults to host `localhost` and database/user/password `cosray`. Override the host with `COSRAY_DB_HOST` when needed.

```bash
sudo -u postgres createuser --pwprompt --createdb cosray
createdb --user cosray --owner cosray cosray
COSRAY_DB_HOST=<your-hostname> ./run migrate --apply
```

System Requirements:

```bash
apt install php8.5 php8.5-pgsql php8.5-gd php8.5-xml php8.5-intl php8.5-curl
```

For development

```bash
apt install php8.5 php8.5-xdebug
```

macOS/homebrew:

```bash
brew install php php-intl
```

## License

Most project files are licensed under [MPL-2.0](LICENSES/MPL-2.0.txt). Files in `panel/` and `ui/` are licensed under [MIT](LICENSES/MIT.txt). See [REUSE.toml](REUSE.toml) for file-level details.

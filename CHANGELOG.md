# Changelog

## [Unreleased](https://codeberg.org/cosray/cms/compare/0.2.0...HEAD)

### Breaking Changes

- Removed the legacy SvelteKit panel (`ui/`), its `/panel` routes, and the `install-panel` command. Downstream apps must switch to the SSR/HTMX panel (`path.panel`, default `/cp`), drop `@php run install-panel` from their composer scripts, and delete the installed `public/panel/` directory.
- Removed the JSON API (`/panel/api` and the optional `path.api` mount) including the auth, user, node, and collection endpoints. Frontend content negotiation (page URLs answered with JSON for `Accept: application/json`) is unaffected; custom API routes can be registered via `Bootstrap::addRoutes()`.
- Renamed the Matrix field concept to Entries/Entry. Use `Cosray\Field\Entries`, `Cosray\Value\Entries`, and `Cosray\Value\Entry`; stored field content now uses `"type": "entries"`.
- Changed Entries fields to use node-style entry schema classes through `#[Allows(...)]` instead of field inheritance. Stored entry items now use an FQCN `type` plus nested `value`, and the panel exposes `entryTypes` metadata. Existing app data needs an app-specific migration to add the entry FQCN for each Entries field.
- `path.panel` now configures the SSR/HTMX panel path and defaults to `/cp`.

## [0.2.0](https://codeberg.org/cosray/cms/src/tag/0.2.0) (2026-06-02)

### Breaking Changes

- Rename the Composer package to `cosray/cms` and the root namespace to `Cosray`.

This release removes the `Node` / `Page` / `Block` / `Document` inheritance hierarchy and dedicated node kind modeling. Content types are now plain PHP classes with metadata attributes, and behavior is derived from route/render conventions.

- **Removed** abstract base classes `Node`, `Page`, `Block`, `Document`.
- **Removed** the `RendersTemplate` trait.
- **Removed** the dead `Fulltext` class.
- **Removed** `#[Page]`, `#[Block]`, `#[Document]` metadata attributes.
- **Changed** routability/rendering semantics to use `#[Route]` and `#[Render]` conventions (renderer fallback remains node handle).
- **Changed** finder facade class from `Cosray\Finder\Finder` to `Cosray\Cms`.
- **Changed** plugin class from `Cosray\Cms` to `Cosray\Plugin`.
- **Changed** CMS configuration ownership. Regular apps can use the new `Cosray\App` facade; advanced manual bootstraps pass `Cosray\Config` to `new Plugin($config)` instead of passing it to `Celemas\Core\App`. `Cosray\Config` no longer implements the removed core config interfaces.
- **Changed** `Cosray\Config` construction to `new Config(string $root, array $settings = [])`. App name, debug mode, environment, app secret, public path, frontend sessions, and database DSN now live in `app.name`, `app.debug`, `app.env`, `app.secret`, `path.public`, `session.enabled`, and `db.dsn` settings instead of constructor arguments or public properties. `path.public` defaults to `$root . '/public'`. `app.name` reads `APP_NAME`, falling back to `celemas`. `session.enabled` reads `SITE_SESSION_ENABLED`. `app.secret` reads `APP_SECRET`. `db.dsn` reads `DATABASE_URL`. `app.name` is not validated or normalized.
- **Changed** `Cosray\View\Boiler\Error\Handler` to read debug/env/error settings from `Cosray\Config`; its constructor now accepts config, factory, and logger.
- **Changed** error integration to use `Celemas\Core\Error` instead of the separate `celemas/error` package; custom error renderers must implement `Celemas\Core\Error\Renderer` and receive a non-null server request.
- **Changed** frontend session middleware configuration from `sessionEnabled` constructor arguments on `Cosray\App` and `Cosray\Plugin` to the `session.enabled` setting.
- **Changed** CMS session options to read `cookie_secure` from `SESSION_COOKIE_SECURE`, `cookie_lifetime` from `SESSION_COOKIE_LIFETIME`, and `gc_maxlifetime` from `SESSION_IDLE_TIMEOUT`.
- **Changed** `Cosray\App::create()` to accept a root path plus an optional settings array, create `Cosray\Config` internally, and expose the config as public `$app->config`.
- **Changed** template embedding API from `find->block(...)` to `cms->render(...)`.
- **Changed** all Field and Value classes to depend on the `FieldOwner` interface instead of the `Node` class.
- **Changed** node type-hints throughout the framework from `Node` to `object`.
- **Changed** the `Node::class` registry tag to `Plugin::NODE_TAG` constant.

### Added

- `#[Name]`, `#[Handle]`, `#[Route]`, `#[Render]`, `#[Title]`, `#[FieldOrder]`, `#[Deletable]`, `#[Permission]` attributes for node metadata.
- `Title`, `HasInit`, `HandlesFormPost`, `ProvidesRenderContext` interfaces for behavioral hooks.
- `FieldOwner` interface decoupling fields from the node hierarchy.
- `FieldHydrator` service for external field initialization (two-phase init).
- `NodeFactory` service for creating node instances via `celemas/wire` autowiring.
- `NodeSerializer` service for node data serialization, blueprint generation, and title resolution.
- `NodeManager` service for node CRUD operations (save, create, delete).
- `PathManager` service for URL path persistence.
- `ViewRenderer` service for rendering nodes to templates.
- `NodeProxy` for template-friendly access to node fields and methods.
- `NodeMeta` caching facade and `Meta` reflection reader for node metadata.
- `NodeFieldOwner` adapter bridging `FieldOwner` with `Context` and uid.
- `Plugin::NODE_TAG` constant replacing the old `Node::class` registry tag.
- Bundled Boiler renderer and error integration under the `Cosray\View\Boiler` namespace. `cosray/cms` now requires `celemas/boiler` directly, so applications no longer need the separate `celemas/cms-boiler` package.
- Default Boiler `view` renderer registration using the new `path.views` config key, which defaults to `/views` relative to `path.root`.
- `Cosray\App` facade for regular CMS applications. It wraps the core app and CMS plugin, forwards the common app and CMS configuration APIs, installs the default error handler, and adds the CMS catchall route during `run()`.
- Built-in fallback templates for Boiler error pages plus `error.*` config keys for enabling/disabling the default handler, replacing the error renderer, configuring error views, and toggling Whoops debug pages.
- Root-based `Config` initialization that loads `.env` with `vlucas/phpdotenv`, sets default `app.name` from `APP_NAME` with a `celemas` fallback, and exposes `Config::requireEnv(...)` for required environment variables.

### Migration guide

Replace inheritance with attributes and implement interfaces as needed:

```php
// Before
class Article extends Page
{
    public Text $title;

    public function title(): string
    {
        return $this->title?->value()->unwrap() ?? '';
    }
}

// After
#[Name('Article'), Route('/{title}')]
class Article implements Title
{
    #[Label('Title'), Translate]
    public Text $title;

    public function title(): string
    {
        return $this->title?->value()->unwrap() ?? '';
    }
}
```

Use the CMS app facade for regular application bootstrapping:

```php
use Cosray\App;

$root = dirname(__DIR__);
$app = App::create($root, [
    'app.name' => 'cms',
    'path.public' => $root . '/public',
]);
$app->section('Content')->collection(\App\Cms\Collection\Pages::class);
$app->node(\App\Cms\Node\HomePage::class);
$app->run();
```

When bootstrapping manually with `celemas/core`, pass the CMS config to the CMS plugin instead of the core app.

Constructor dependencies are autowired from the Registry via `celemas/wire`:

```php
#[Name('Department'), Route('/{title}')]
final class Department implements Title
{
    public function __construct(
        protected readonly Request $request,
        protected readonly Cms $cms,
    ) {}

    #[Label('Title'), Required, Translate]
    public Text $title;

    public function title(): string
    {
        return $this->title?->value()->unwrap() ?? '';
    }
}
```

## [0.1.1](https://codeberg.org/cosray/cms/src/tag/0.1.1) (2026-02-01)

Codename: Benjamin

- Added support for installing the panel from tagged releases (including alpha/beta/rc), instead of only nightly builds.
- Improved the `install-panel` command output and removed the unnecessary Quma command dependency.
- Updated the panel release workflow to support prerelease tag patterns and manual (retroactive) runs.

## [0.1.0](https://codeberg.org/cosray/cms/src/tag/0.1.0) (2026-02-01)

Initial release - Codename: Sabine

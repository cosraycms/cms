# Application setup

This is a guide to the current API. For signatures and defaults, start with [App](../src/App.php), [Config](../src/Config.php), and [Config\Defaults](../src/Config/Defaults.php).

## Configuration

Pass the project root and a settings array to `App::create()`. The app exposes the resulting immutable configuration as `$app->config`; construct settings before boot because routes and services consume them during setup. `Config::with()` returns a changed copy, not a mutation of the running app.

```php
use Cosray\App;

$root = dirname(__DIR__);
$app = App::create($root, [
    'app.name' => 'mycms',
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
| `panel.path` | Panel URL path; defaults to `/panel` |

`path.assets` and `path.cache` couple directories below the document root to media URL paths. Either directory may be a symlink to one outside the document root, for example to keep uploads and renditions in a directory shared between deployed releases; the web server must then follow symlinks. With `app.url_prefix => '/site'`, an original below `$root/public/assets` has a URL starting with `/site/assets/`. The router applies the prefix to routes, but panel-generated links and login redirects do not consistently include non-empty prefixes yet.

The panel, media and preview routes answer before any node, so node paths at or below `panel.path`, `path.assets`, `path.cache` or `/preview` are refused when saved. Routes a plugin registers are not checked. Changing one of these settings does not move existing nodes out of the way; a node already stored below the new prefix becomes unreachable.

## Views and errors

Cosray bundles Boiler as the default `view` renderer, reading from `{path.root}{path.views}`. To replace it or pass renderer arguments, register it before boot:

```php
use Cosray\View\Boiler\Renderer;

$app->renderer('view', Renderer::class)->args(
    dirs: __DIR__ . '/custom-views',
    defaults: ['siteName' => 'My Site'],
);
```

Templates render escaped: Boiler escapes the strings and objects that reach them, including what their properties and methods return. Field values ([Value](../src/Value/Value.php)) and [Markup](../src/Markup.php) print as is, because values escape plain text in their own string conversion and methods that produce HTML, such as `$node->image->tag()`, `$node->content->render()`, or `$cms->menu('main')->html()`, return `Markup`. Every Cosray Boiler renderer trusts both, so `trusted:` lists only a site's own classes whose string conversion is safe HTML. A site method that produces HTML returns `new Markup($html)` and escapes what it inserts.

A field's `raw()` returns its value unescaped. Numbers, booleans, and null come back as they are; strings, arrays, and objects come back wrapped again, so compare `u(...)` or use `->is()`, and pass `u(...)` to functions that need the original. A site's own PHP functions and classes that get values from templates unwrap them with [`Celema\Boiler\unwrap()`](https://codefloe.com/celema/boiler/src/branch/main/docs/values.md#your-own-php-code). Boiler's [common pitfalls](https://codefloe.com/celema/boiler/src/branch/main/docs/pitfalls.md) and [values documentation](https://codefloe.com/celema/boiler/src/branch/main/docs/values.md) cover comparisons, PHP functions, and JSON.

Error pages use a separate Boiler renderer. Project `http-error.php` and `http-server-error.php` templates override the built-in fallbacks. A request that finds the database unreachable gets `http-error.php` with status 503 and `Retry-After: 30` and is logged as an alert; PostgreSQL and MySQL connections give up after 5 seconds unless `db.options` sets `PDO::ATTR_TIMEOUT`. A request that accepts only JSON gets an error's payload, or `{"status": …, "message": …}` when it has none. Set `error.enabled` to `false` when installing custom error middleware. The lower-level core app and CMS bootstrap remain accessible through `$app->core()` and `$app->bootstrap()`.

## Logging

Pass a PSR-3 logger to `$app->logger()` before the app handles its first request. Without one, records from `notice` up go to PHP's error log. Services and controllers receive the logger by declaring a `Psr\Log\LoggerInterface` constructor parameter.

Besides server errors and PHP diagnostics, Cosray logs logins (failed ones as `warning`, naming the account only when it exists), panel logins refused for missing permission, logouts, the creation and deletion of users and changes to their roles, activation and password (`notice`), and uploads the server could not store (`error`). Client addresses are `REMOTE_ADDR`; behind a reverse proxy they are the proxy's.

## Serving requests

The front controller boots the app and serves it:

```php
// public/index.php
$app = require dirname(__DIR__) . '/boot/app.php';

return $app->serve();
```

`serve()` handles the current request under PHP-FPM, FrankenPHP's classic mode and the development server, and handles requests until the worker retires when the script runs as a [FrankenPHP worker](#worker-mode). `run()` handles exactly one request; tests and scripts use it. Every request runs in its own container scope and ends with a teardown that resets scoped services and rolls back a transaction the request left open. The lifecycle, the failure handling and the worker limits are described in [Celema core](https://codefloe.com/celema/core#request-lifecycle).

Register plugins, services and routes before the first request. The container is sealed after it, and later registrations throw.

### Worker mode

A FrankenPHP worker boots the app once and keeps it in memory for many requests, which saves the boot and the database connection setup per request. Point the worker at the normal front controller and set the number of workers explicitly:

```caddyfile
example.org {
	root * /srv/site/public
	php_server {
		worker {
			file /srv/site/public/index.php
			num 4
		}
	}
}
```

What lives for the worker and what for one request:

- Booted once: configuration, routes, middleware, schemas and registries, plugins, renderers (including error and panel renderers), dashboard cards registered as objects, and every service registered with `$app->register()`, which is shared unless declared `->scoped()` or `->transient()`.
- Per request: controllers, `Context` and `Cms`, nodes, embedded objects, collections, block types, dashboard cards registered as class names, the session and its user, the locale and translator, and `scoped()` services.

Code that outlives a request must not keep the state of one: no request, user, locale or content in properties of registered services, renderers, middleware or static properties. A shared service cannot depend on a scoped one; the container throws instead of keeping the first request's instance.

Objects Cosray builds for a request can ask for request data in their constructors: nodes, embedded objects, collections, block types and dashboard cards get the `Request`, `Context`, `Config` and `Database` through [Context::create()](../src/Context.php), and controllers get them through the router. Registered services do not get the request; pass it to them as a method argument.

More rules for request code:

- Finish a request by returning a response. `exit()` and `die()` end the worker, which then boots again.
- The database connection stays open between requests and is rolled back to a clean state after each one. Set `db.reuse` to `false` to disconnect after every request instead. Keep connection settings transaction-local (`SET LOCAL`) and budget the connections for the workers of all sites sharing a database server; see [Quma's notes on long-running processes](https://codefloe.com/celema/quma/src/branch/main/docs/long-running-processes.md).
- Sessions are closed when a request ends.
- Cosray reads environment variables at boot. FrankenPHP rebuilds `$_SERVER` for every request, so values a `.env` loader wrote into `$_SERVER` at boot are not there in requests; read them through the configuration or `$_ENV`.

Content, users, permissions and media are read per request. Changes to code, templates, configuration, SQL files, translation catalogs, plugins and `.env` reach the workers when they restart, so restart FrankenPHP or its workers after a deployment.

## Console commands

[Cosray\Console\Commands](../src/Console/Commands.php) registers the migration commands, index rebuilds, panel asset publishing, the superuser and token commands, and the [job queue](queue.md) commands. `php run help` lists the commands actually registered by the application; app-specific commands resolve lazily.

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

- `server()` registers the development server with `frankenphp:install` and `reload` when the optional `celema/server` package is installed. `php run server` runs the built-in PHP server, `php run server frankenphp` FrankenPHP with its embedded PHP runtime; `server: 'frankenphp'` makes FrankenPHP the default, `frankenphp:` pins its release, like `'1.12.7'`, or names an executable, and `companions:` start processes alongside, like asset watchers. Arguments left out keep the server's defaults, such as port 2130 and its watch patterns. `reload` serves live reload for an application served elsewhere.
- `i18n()` registers synchronization and status commands for a translation domain, scanning source paths and the app's schema labels. Cosray's own labels, such as that of the built-in all-content collection, are left to Cosray's catalogs. Call it per domain when needed.
- `add()` accepts instances, class names, or keyed factories for commands needing custom scalar arguments.

The server watches files by default and updates open pages when a file matching the `watch` patterns changes: it swaps changed stylesheets in place and morphs pages into a freshly rendered copy, which keeps the scroll position and form input. The panel reloads instead. `php run server --no-watch` turns off watching, live reload, and the restart of FrankenPHP workers. Pages opt in by including the live reload script in the site's base layout, before `</body>`:

```php
<?= $cms->liveReload() ?>
```

It renders nothing unless the dev server is watching, so it can stay in production layouts. The script URL uses the host the page was requested under, so live reload also works on other devices or in virtual machines when the server listens there, for example with `--host=0.0.0.0`. The [`celema/server` README](https://codefloe.com/celema/server) covers watch patterns, ports, and how pages opt out of morphing.

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

## Icons

Icon ids such as `#[Icon('bi:check')]` resolve through the icon providers in order. By default these are the site's own SVG files below `icons.local.paths`, where `bi:check` means `bi/check.svg` and an unprefixed `logo` means `logo.svg`, then the [Iconify API](https://iconify.design/) for prefixed ids. `$app->icons()` adds a provider before them or, with `replace: true`, replaces both. Iconify responses are cached below `{path.public}{path.cache}/icons/`. Pages include icon markup inline, so icons can use `currentColor`.

Local files are used as they are. Iconify markup is sanitized like an [SVG upload](media.md#svg-uploads), and because inline SVG shares the page's CSS and layout, it also loses all comments (legal comments included), `<style>` elements, the root element's `style`, `transform`, `overflow`, and `filter`, and animations of the root element. This does not affect icons drawn with paths and `currentColor` or animated with SVG animation elements such as `<animate>` and `<animateTransform>`. Icons that are styled or animated by a stylesheet, for example `@keyframes` in a `<style>` element as some spinner sets use, lose that styling: they render static or with default fills.

Prefer static icons, and for animation choose icons that use SVG animation elements; check an animated icon where it is shown before relying on it. To use an icon that needs its stylesheet, review it and place a copy below a local icon path.

Iconify responses carry no license notices. Check the license of each icon set you use; some require attribution on the site.

## Panel assets and theming

The panel's browser files ship with the package as they are, so `composer install` or `update` is the whole installation. PHP serves `panel/src`, `panel/styles`, `panel/icons` and the vendored third-party modules in `panel/modules` under `{panel.path}/assets/{revision}/`, plus an icon sprite it builds from `panel/icons` and, below `composer/`, the modules other Composer packages ship, such as verba's translation runtime from `celema/verba`. The revision derives from the commits Composer installed for Cosray and those packages, or from a hash of the version for a package without one. In debug mode and in a Git working copy, such as a symlinked path repository, the revision is instead a hash over the files themselves, so every edit gets new URLs. Responses for the current revision may be cached for a year (`immutable`); for any other they are revalidated through an ETag.

A first visit loads the modules one by one: about 90 files for the dashboard and 130 for an editor with rich text and code, around 200 and 700 KB compressed. Make sure the web server compresses `text/javascript` and `text/css` responses (gzip, Brotli or zstd) and speaks HTTP/2; uncompressed, the same visits move several times the bytes. Later visits load nothing but the page.

### Publishing for static delivery

Optionally let the web server deliver the panel files directly:

```bash
php run panel:publish
```

The command copies the browser files from the installed packages and generates the icon sprite at `{path.public}{panel.path}/assets/{revision}/` (by default, `public/panel/assets/{revision}/`). It includes the Composer-provided browser modules, but not PHP views, package configuration, or plugin assets. No new settings or URL changes are needed; `app.url_prefix` does not add a filesystem directory.

Run it after `composer install` or `composer update` in deployment, using the same configuration and package files as the web application. A complete revision is published at once. Repeating the command verifies an existing publication without overwriting it; a conflicting or incomplete directory causes an error. Older revisions and unrelated application files are left untouched. Remove obsolete revision directories separately when they are no longer needed for open browser tabs or rollbacks. Publishing is intended for production installs; during development, PHP serves edits without needing another publish step.

Configure the web server to serve existing **files** directly and route other requests, including the panel's directory paths, to PHP. Disable directory listings, serve `.js` as `text/javascript`, and configure compression and `Cache-Control: public, max-age=31536000, immutable` for the published revision files. Keep the ordinary PHP fallback: it still serves unpublished revisions and plugin assets. No server configuration files are generated.

`panel.theme` accepts a stylesheet URL or a list of URLs. [Panel styles](panel-styles.md) explains the cascade and current theming approach. [Panel development](../panel/README.md) covers the panel's checks and the live styleguide.

## Users, roles and permissions

Roles and allow entries live in code; the database stores each user's role names. [Security\Policy](../src/Security/Policy.php) resolves principals (`everyone`, `authenticated`, `user:{uid}`, `type:{handle}`, `role:{name}`) against permissions. Grants from multiple roles combine; unknown roles grant nothing.

```php
$app->role('shop', 'Shop manager');
$app->allow('role:shop', 'panel', 'edit-orders');
$policy->permits($user, 'edit-orders');
```

Routes use `#[Permission(...)]` middleware; panel controllers can call `$this->permits(...)`. The Users area needs `edit-users`. Its write policy prevents editors from granting access beyond their own, changing their own roles/state, or removing the last active superuser. Password changes end other sessions and remembered logins. The profile page allows self-service account changes, with the current password required for a new one.

Users have an email login and optional username without `@`. A subclass of [Cosray\User](../src/User.php), registered through `$app->user()`, adds fields like a node class. `#[Roles(...)]` limits assignable roles; `#[Roles]` allows none. Fields are neutral-locale values stored under `content` in `users.data`, without working copies, and hydrate on demand through [User\Fields](../src/User/Fields.php). See [User\Types](../src/User/Types.php) and its tests for type registration and default-type replacement.

### Node permissions

A node type maps the actions `read`, `create`, `change` and `delete` to permissions. By default, routable types read with `read-pages`, which the default policy grants to `everyone`, and other types with `read-nodes`, which editors, admins and superusers hold; every write needs `edit-nodes`. `read` covers published, undeleted nodes, while any other state takes the type's `change` permission.

```php
#[Permission(['read' => 'read-pages'])] // a type without a route that anyone may read
final class Footer {}

#[Permission(['read' => 'read-nodes', 'change' => 'edit-catalog'])]
final class Product {}
```

Actions a type leaves out keep their defaults, and a single string applies to every action. The website serves any published node with a path, but a type that declares a read permission, its default one included, is served only to users who hold it, on its page URLs as in the API. [Security\NodeAccess](../src/Security/NodeAccess.php) evaluates them. The [API](api.md) enforces them; the panel still only checks `panel`.

### API tokens

A user holds any number of named API tokens. A token acts as its user, so the user's roles decide what it may do; a deactivated or deleted user's tokens stop working with the account. Requests send it as `Authorization: Bearer <token>`. Only its sha256 is stored, so the token is shown once, when it is created. Tokens optionally expire, and the time of their last use is recorded to the minute.

```bash
php run token:create editor@example.com --name="Shop import" --expires=2027-01-31
php run token:list editor@example.com
php run token:revoke editor@example.com <uid>
```

Panel users manage their own tokens on the profile page, under API tokens. [User\Tokens](../src/User/Tokens.php) is the PHP API. Integrations usually get a user of their own with just the roles they need. Migration `000000-000043-api-tokens.sql` turns the former single token per user into this table and keeps existing tokens; code that maintains `auth_tokens` itself must now also write a unique `uid`, and should write a `name`.

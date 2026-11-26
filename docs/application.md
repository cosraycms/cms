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

The complete settings list lives in [Defaults.php](../src/Config/Defaults.php); conversions and validation live in [Config/](../src/Config/). `session.enabled` controls frontend session middleware. The panel has its own session handling. `app.timezone` is the default time zone of panel timestamps; each user can choose another.

`app.name` names the session cookies, so changing it signs everyone out. The site's name as people read it, in mails for example, is `app.title`, `APP_TITLE` in the environment; it defaults to the host of `app.url`, else `app.name`.

### Search engines

`app.indexable`, `APP_INDEXABLE` in the environment, is true by default. Set it to false on a copy of a site that search engines must not list, such as a staging server: `/robots.txt` then answers `Disallow: /` for every crawler, even where `public/robots.txt` exists, and every response the application renders carries `X-Robots-Tag: noindex, nofollow`. Files the web server serves itself, such as uploads and renditions, fall under the robots.txt rule alone; add the header in the web server to cover them too. The web server must pass `/robots.txt` to the application when no such file exists, which a general fallback to `index.php` does and a location that answers `/robots.txt` itself prevents.

The two signals limit each other: a crawler that obeys `Disallow` never fetches a page and so never sees its `noindex`, and may still list an address it found linked elsewhere, without its content. That is enough to keep a fresh staging site out of search results. To remove pages a search engine has already listed, they have to stay crawlable until it has seen the header. Neither signal restricts access; a site that must stay private needs authentication.

### Database namespace

Cosray keeps its tables in a namespace of their own: the PostgreSQL schema `cms` by default, which Cosray creates before its install migrations unless it exists, and the table-name prefix `cms_` on other drivers. The `cms.prefix` placeholder sets it. On PostgreSQL, a value ending in a dot names a schema; any other value is prepended to each table name.

```php
$app = App::create($root, [
    'db.placeholders' => [
        'pgsql' => ['cms.prefix' => 'cms.'],
    ],
]);
```

An empty prefix is possible but strongly discouraged. Cosray's tables have short, plain names such as `users`, `types`, `nodes`, `tags`, `jobs` and `drafts`, which a site's own tables or another package may want as well. A namespace keeps them apart and shows at a glance which tables Cosray owns and migrates, so site code and site migrations do not change them by accident. It also lets backups, permissions and maintenance address Cosray's tables as a whole, for example with `pg_dump --schema=cms`, and site SQL names them unambiguously, as in `cms.nodes`. Choose the namespace before installing: changing it later means moving every table, for example with `ALTER TABLE … SET SCHEMA`.

The unqualified names, `public` on PostgreSQL, are the site's own. Name the database role unlike any of these schemas: PostgreSQL's default search path looks in a schema named like the role first, so a role `cms` would put the site's unqualified tables into Cosray's schema. Each [plugin](#plugins) with tables gets a namespace of its own, in the same form as Cosray's: a schema where Cosray has one, a name prefix where Cosray has that.

### Filesystem directories and URL paths

| Setting | Meaning |
| --- | --- |
| `path.root` | Project filesystem root supplied to `App::create()` |
| `path.public` | Filesystem document root; defaults to `$root/public` |
| `path.views` | View directory relative to `path.root`; `/views` means `$root/views` |
| `path.assets` | Originals below `path.public`, also their URL path; defaults to `/assets` |
| `path.cache` | Renditions below `path.public`, also their URL path; defaults to `/cache` |
| `app.url_prefix` | Application URL mount prefix; does not change filesystem locations |
| `app.url` | Absolute URL the application is served under, like `https://example.org`, for links in mails and for absolute URLs without a request, as in jobs and commands; `APP_URL` in the environment. Never taken from a request's host |
| `app.trusted_proxies` | IP addresses and CIDR ranges of reverse proxies, like `['127.0.0.1', '10.0.0.0/8']`; `TRUSTED_PROXIES`, comma-separated, in the environment. A request from one of them takes its client address from `X-Forwarded-For` |
| `panel.path` | Panel URL path; defaults to `/panel`. The path with a trailing slash redirects to it |

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

Error pages use a separate Boiler renderer. Project `http-error.php` and `http-server-error.php` templates override the built-in fallbacks, as `maintenance.php` does for [maintenance mode](maintenance.md#maintenance-page). A request that finds the database unreachable gets `http-error.php` with status 503 and `Retry-After: 30` and is logged as an alert; PostgreSQL and MySQL connections give up after 5 seconds unless `db.options` sets `PDO::ATTR_TIMEOUT`. A request that accepts only JSON gets an error's payload, or `{"status": …, "message": …}` when it has none. Set `error.enabled` to `false` when installing custom error middleware. The lower-level core app and CMS bootstrap remain accessible through `$app->core()` and `$app->bootstrap()`.

## Logging

Without further setup, Cosray appends records to `var/log/app.log` in the project root and creates the directory when it is missing. `log.file`, or `LOG_FILE` in the environment, names another file; an empty `LOG_FILE` sends records from `notice` up to PHP's error log instead, for hosts that collect it. `log.level`, or `LOG_LEVEL`, sets the lowest level written to the file, `debug` by default; production usually wants `warning`. Keep the file out of version control, and let the host rotate it, for example with `logrotate`.

To log elsewhere, pass a PSR-3 logger to `$app->logger()` before the app handles its first request; it replaces the default. Services and controllers receive the logger by declaring a `Psr\Log\LoggerInterface` constructor parameter.

Besides server errors and PHP diagnostics, Cosray logs logins (failed ones as `warning`, naming the account only when it exists), panel logins refused for missing permission, sign-ins and reset requests refused as too many (`warning`), logouts, the creation and deletion of users and changes to their roles, activation and password (`notice`), and uploads the server could not store (`error`). Client addresses are `REMOTE_ADDR`. Behind a reverse proxy, that is the proxy's address unless `app.trusted_proxies` lists it: then the client address is the last one in `X-Forwarded-For` that is not a trusted proxy. List only proxies that replace or append to that header, since clients can send it themselves.

To find slow queries, set `db.slow` to a number of seconds, such as `0.5`. Every database statement that takes at least that long is then logged as a `warning` with its duration and SQL file, or its SQL when it has none. Bound values are not logged. The default, `null`, logs no statements.

## Serving requests

The front controller boots the app and serves it:

```php
// public/index.php
$app = require dirname(__DIR__) . '/config/boot.php';

return $app->serve();
```

`serve()` handles the current request under PHP-FPM, FrankenPHP's classic mode and the development server, and handles requests until the worker retires when the script runs as a [FrankenPHP worker](#worker-mode). `run()` handles exactly one request; tests and scripts use it. Every request runs in its own container scope and ends with a teardown that resets scoped services and rolls back a transaction the request left open. The lifecycle, the failure handling and the worker limits are described in [Celema core](https://codefloe.com/celema/core#request-lifecycle).

Register plugins, services and routes before the first request. The container is sealed after it, and later registrations throw.

### Web server

Behind nginx, PHP-FPM runs the front controller. A server block that covers what Cosray expects:

```nginx
server {
    # From nginx 1.25.1 on, `http2 on;` replaces the http2 parameter
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name example.org;
    # ssl_certificate and ssl_certificate_key, for example from certbot

    root /var/www/example.org/current/public;
    index index.php;
    charset utf-8;

    # As large as the largest upload, upload.maxsize.video
    client_max_body_size 256m;

    gzip on;
    gzip_vary on;
    gzip_types text/css text/javascript application/javascript application/json image/svg+xml;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
        try_files $uri /index.php$is_args$args;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known/) {
        deny all;
    }
}
```

- Every request without a file of its name goes to `index.php`, which also serves the panel, media renditions and the API.
- `/robots.txt` needs that fallback too, where the site has no such file and is not [indexable](#search-engines). A location that answers it without `try_files`, as common templates have one, ends in nginx's own 404.
- Only the front controller runs. Any other PHP file answers 404, also one that found its way into the uploads below `public/assets`.
- `$realpath_root` resolves a symlinked document root per request, so a deployment that switches a `current` link runs each request entirely in one release.
- The panel loads its modules one by one, so it needs HTTP/2 and compression for CSS and JavaScript; see [Panel assets and theming](#panel-assets-and-theming).
- PHP's `upload_max_filesize` and `post_max_size` must allow uploads as large as `client_max_body_size`; see [Video](media.md#video).

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

[Cosray\Console\Commands](../src/Console/Commands.php) registers the migration commands, index rebuilds, panel asset publishing, the user and token commands, the [job queue](queue.md) commands, `mail:test` for the [mail](mail.md) setup, the [maintenance mode](maintenance.md) commands, and `system:info`, which reports what the [system info](#system-info) page does, for the console's own PHP, and exits with 1 on a problem; `--json` prints it as JSON. `php run help` lists the commands actually registered by the application; app-specific commands resolve lazily.

A project `run` script can load a `config/console.php` returning the runner:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

exit((require __DIR__ . '/config/console.php')->run());
```

```php
<?php

declare(strict_types=1);

use Cosray\Console\Commands;

$app = require __DIR__ . '/boot.php';
$commands = new Commands($app);
$commands->server(port: 6913, watch: ['src/**/*.php', 'views/**/*.php']);
$commands->i18n('mysite', locales: ['de', 'en'], scan: ['src', 'views']);
$commands->add(\App\Command\Import::class);

return $commands->runner();
```

- `server()` registers the development server with `frankenphp:install` and `reload` when the optional `celema/server` package is installed. `php run server` runs the built-in PHP server, `php run server frankenphp` FrankenPHP with its embedded PHP runtime; `server: 'frankenphp'` makes FrankenPHP the default, `frankenphp:` pins its release, like `'1.12.7'`, or names an executable, and `companions:` start processes alongside, like asset watchers. Arguments left out keep the server's defaults, such as port 2130 and its watch patterns. `reload` serves live reload for an application served elsewhere.
- `i18n()` registers synchronization and status commands for a translation domain, scanning source paths and the schema labels of the app's node types, the block types it registers and the row and block types their fields allow. Cosray's own labels, such as that of the built-in all-content collection, are left to Cosray's catalogs. Call it per domain when needed.
- `add()` accepts instances, class names, or keyed factories for commands needing custom scalar arguments.

The server watches files by default and updates open pages when a file matching the `watch` patterns changes: it swaps changed stylesheets in place and morphs pages into a freshly rendered copy, which keeps the scroll position and form input. The panel reloads instead. `php run server --no-watch` turns off watching, live reload, and the restart of FrankenPHP workers. Pages opt in by including the live reload script in the site's base layout, before `</body>`:

```php
<?= $cms->liveReload() ?>
```

It renders nothing unless the dev server is watching, so it can stay in production layouts. The script URL uses the host the page was requested under, so live reload also works on other devices or in virtual machines when the server listens there, for example with `--host=0.0.0.0`. The [`celema/server` README](https://codefloe.com/celema/server) covers watch patterns, ports, and how pages opt out of morphing.

Commands can inject `Cms`, `Context`, `Config`, `Database`, `Locales`, and `Cosray\Node\Writer`. They run with the default content locale and a Verba translator but without an HTTP request or session; a node they build gets a `Request` stand-in that throws once it is used. Use `Context::withLocale()` for locale-specific work.

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

### Content migrations

A site's PHP migrations ask for the same services in their constructors as commands do. Cosray builds them on the migration's own connection, so their writes join the transaction `php run db:migrations --apply` runs the migrations in: a failing migration leaves the content as it was. Each write becomes a new revision with an event, as any other write through the store does. [Api\Writer](../src/Api/Writer.php)`::patch()` changes fields of an existing node like a `PATCH` through the [API](api.md#nodes), on the main version and an editor's working copy alike, so applying that copy later keeps the change:

```php
<?php

declare(strict_types=1);

namespace App\Migrations\AboutSubtitle;

use Celema\Quma\Contract\Migration as MigrationContract;
use Celema\Quma\Environment;
use Cosray\Actor;
use Cosray\Api\Writer;
use Cosray\Cms;

final class Migration implements MigrationContract
{
    public function __construct(
        private readonly Cms $cms,
        private readonly Writer $writer,
    ) {}

    public function run(Environment $env): void
    {
        $this->writer->patch(
            $this->cms->node->byUid('about', published: null),
            ['content' => ['subtitle' => ['value' => ['en' => 'Who we are']]]],
            Actor::system(),
        );
    }
}
```

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

A plugin is PHP code that runs in the site's process with the site's full access. There is no sandbox, so installing a plugin means trusting its code. The `plugins` config key also accepts plugin registrations. Classes registered by name are constructed without arguments; register a pre-built instance when construction needs arguments. IDs consist of lowercase letters, digits, and dashes, start with a letter and need a dash, which keeps plugin config namespaces separate from built-in settings and makes the id a valid database namespace.

Settings use flat namespaced keys, such as `'acme-shop.currency' => 'USD'`. Within registration, `$cms->option('currency', 'EUR')` reads that key with a default; elsewhere, use `Config`. Custom field classes extend `Cosray\Field\Field`, as [writing a field type](content.md#writing-a-field-type) describes; `Registrar::field()` string aliases are for legacy imports, not required when node properties use the field class directly.

`$cms->nodeSchema(Stocked::class, new StockedHandler())` gives node types a class attribute of the plugin's own, such as `#[Stocked]`. The handler extends [Node\Schema\Handler](../src/Node/Schema/Handler.php) and returns the type properties the attribute stands for, like `['stocked' => true]`, which code reads with `Types::get($class, 'stocked', false)`. It applies to every type that carries the attribute, the site's own included, whenever they were registered.

`$cms->commands(ImportOrders::class)` adds console commands, as `Commands::add()` takes them: class names are autowired in the console scope, and keyed factories build a command themselves. Command names are free; a name another command already uses fails when the console starts.

`$cms->translations(__DIR__ . '/../lang')` registers the translation domain `{id}`, with catalogs named `{id}.{locale}.php`. It is searched after the app's domains and before Cosray's, so an app can still shadow a plugin's entry. Keys start with the plugin id by convention, such as `acme-shop:order-shipped`. A panel language the plugin ships no catalog for shows the entries of the next language in the panel's fallback chain, usually English. The panel sends only the `panel` domain to its scripts; pass `scripts: true` to send the plugin's catalog too, embedded in every full page load. A plugin repo registers Verba's `i18n:sync` and `i18n:status` for its own domain in its `run` script.

`$cms->migrations($dir)` puts the plugin's migrations into a Quma namespace named after the plugin. They are recorded as `{id}:{file}`, such as `acme-shop:001-orders.sql`, so a plugin numbers its files itself. `php run db:migrations --apply` applies the site's and Cosray's migrations of `default` first, then each plugin's namespace in registration order, all in one transaction: a failing migration leaves the database unchanged, and a plan without `--apply` lists every namespace. The id is stored with these records, so renaming a plugin's id means renaming its records.

A plugin's migrations run after all of `default`, on an old database as on a fresh install, so they always meet the current core schema, not the one they were written against. Keep a migration's queries in its file instead of calling Cosray classes.

A plugin never creates tables, indexes or functions in Cosray's database namespace. Each plugin with migrations or SQL has a namespace of its own, named after its id in snake case, and its SQL reaches it through two placeholders: `{id}.prefix` for tables and `{id}.obj` for the names of indexes, triggers and constraints:

```sql
CREATE TABLE /*:acme-shop.prefix:*/orders (
    "order" bigint GENERATED ALWAYS AS IDENTITY,
    customer bigint NOT NULL,
    CONSTRAINT /*:acme-shop.obj:*/pk_orders PRIMARY KEY ("order"),
    CONSTRAINT /*:acme-shop.obj:*/fk_orders_users FOREIGN KEY (customer)
        REFERENCES /*:cms.prefix:*/users (usr)
);
CREATE INDEX /*:acme-shop.obj:*/ix_orders_customer ON /*:acme-shop.prefix:*/orders (customer);
```

The namespace takes the form Cosray's own has (see [database namespace](#database-namespace)). On PostgreSQL that is a schema, `acme_shop.orders`, which Cosray creates before the plugin's pending migrations run, or leaves alone where an administrator created it for a role that may not. Where the site gave Cosray a name prefix such as `cms_`, and on other drivers, the plugin's is a name prefix too: `acme_shop_orders`, with `acme_shop_` in front of each index name as well. So table names need no plugin id of their own. A site sets another namespace with `'acme-shop.prefix'` in `db.placeholders`. A namespace that is empty, equals Cosray's or another plugin's, or lies inside one, as `cms_shop_` does inside `cms_`, stops the app at boot.

A plugin may reference `users (usr)` in Cosray's namespace and read `users.uid`, `username`, `email`, `data->>'name'` and `deleted`; users are only marked deleted, so such a foreign key never blocks deleting one. It may also reference `nodes (uid)`, a node's stable id, with `ON DELETE CASCADE`: nodes are only marked deleted too, and a cascade keeps a later purge from stopping at the plugin's rows. Read the nodes themselves through the finder, not from the table. Name the plugin's SQL folder after the plugin, `acme_shop`, so queries read `$db->acme_shop->orders()`: Quma takes the first folder of a name it finds and searches Cosray's first, so a folder named like one of Cosray's or another plugin's loses without an error. Cosray offers no uninstall; a plugin's docs say how to drop its schema or tables.

### Panel pages and navigation

A plugin's panel pages are routes below `{panel.path}/{id}`, registered with `panel()`:

```php
use Cosray\Plugin\PanelRoutes;

$cms->templates(__DIR__ . '/../views');
$cms->panel(static function (PanelRoutes $panel): void {
    $panel->get('', [\Acme\Shop\Panel\Orders::class, 'index'], 'orders')->render('acme-shop:orders');
    $panel->get('/{order}', [\Acme\Shop\Panel\Orders::class, 'show'], 'order')->render('acme-shop:order');
    $panel->post('/{order}/ship', [\Acme\Shop\Panel\Orders::class, 'ship'], 'ship');
});
$cms->section('Shop')->link('Orders', $cms->panelUrl())->permission('acme-shop.edit-orders');
$cms->allow('role:admin', 'acme-shop.edit-orders');
```

`get()`, `post()`, `put()`, `patch()` and `delete()` take a pattern below the plugin's base, the endpoint and a name; routes are named `cms.panel.{id}.{name}`. They run behind the panel's session, language, [CSRF check](#csrf-protection) and sign-in, as built-in pages do. `render()` renders the context the controller returns with a panel template; without it, the controller returns a response, such as a redirect or a download. `$cms->panelUrl('/orders')` builds `{panel.path}/{id}/orders`.

Plugin ids need a dash, and every built-in panel route starts with a one-word segment, so a plugin's pages never meet one of core's. The id also names the plugin's template namespace, translation domain and migration namespace, and its permissions are written `{id}.{name}`, such as `acme-shop.edit-orders`. A permission exists once `allow()` grants it; `#[Permission]` and `permission()` name it in full.

`$cms->assets(__DIR__ . '/../assets')` serves the plugin's browser files under the panel revision, at `{panel.path}/assets/{revision}/plugins/{id}/…`, and `panel:publish` copies them with the panel's own files. `$cms->css('shop.css')`, `$cms->js('shop.js')` and `$cms->control()` take a path in that directory; a path that starts with `/` or a scheme is a URL and used as it is. The revision includes each plugin's: the commit Composer installed for its package, or, for a plugin inside the project, the state of its asset files. The unversioned route `{panel.path}/vendor/{id}/…` stays for URLs built by hand, cached for an hour. Plugin stylesheets prefix their classes with the plugin id and never define `cms-` classes.

Plugin panel templates can also draw a block type on the editor canvas, see [editor views](blocks.md#editor-views).

Panel controllers extend [Panel](../src/Controller/Panel/Panel.php) and return `$this->context([...])`; the [panel kit](panel-kit.md) lists the components and controller methods a plugin page builds on; `#[Permission]` on a method refuses users without that permission. `$this->url('/42', ['tab' => 'items'])` builds a URL below the plugin's pages, `{panel.path}/{id}/42?tab=items`. Their templates call `$this->layout('layer/main')` and render only their fragment, without another `id="main"`. The [layer templates](../panel/views/layer/) wrap it for the requested swap:

- `main`: content inside an area.
- `frame`: rail and content on an area switch.
- `shell`: panel chrome for history restore or a full htmx render, without scripts.
- `document`: a complete page for an ordinary request.

A page can also join the system area that the masthead's gear opens. `system()`, on the app or a plugin's registrar, adds its link to that rail, and the controller names the area:

```php
$cms->panel(static function (PanelRoutes $panel): void {
    $panel->get('/imports', [\Acme\Shop\Panel\Imports::class, 'index'], 'imports')->render('acme-shop:imports');
});
$cms->system()->link('Imports', $cms->panelUrl('/imports'))->icon('bi:upload')->permission('acme-shop.import');

final class Imports extends \Cosray\Controller\Panel\Panel
{
    protected const string AREA = 'system';

    #[\Cosray\Middleware\Permission('acme-shop.import')]
    public function index(): array
    {
        return $this->context(['title' => 'Imports']);
    }
}
```

System links appear between Users and System info on one side and the profile on the other, in registration order unless their `meta->order` differs. In either rail, `permission()` only hides a link from users without that permission, and a section left empty disappears; the route enforces the permission through `#[Permission]`. A page without `AREA` belongs to the content area. A link is current on its URL and on every page below it; when several links of a rail match, the longest wins. Pass `activePrefix` only for a link that should be current outside its own subtree.

Existing nodes open at `{panel.path}/node/{uid}`; creation uses `{panel.path}/node/create/{type}`. Build URLs through [NodeUrls](../src/Panel/NodeUrls.php):

```php
$links = new \Cosray\Panel\NodeUrls($config->panel->path);
$editUrl = $links->edit($uid);
$createUrl = $links->create('article', parent: $parentUid);
```

Optional `from=collection:{handle}` or `from=dashboard` supplies return navigation, not authorization. Collection list state lives under `list[...]`; creation's top-level `parent` is independent of `list[parent]`. Links switching panel areas target `#frame`.

These endpoints require panel access. Creation accepts registered types, with an explicit parent checked against `#[Children(...)]`. Collection blueprints restrict displayed choices, not endpoint access; a separate node permission policy is not enforced.

### Client lifecycle

Registered scripts load once per full document and do not run again after htmx navigation. Delegated, idempotent listeners or custom elements work across replaced fragments; load-time DOM decoration alone does not. Panel markup is internal unless an extension boundary is documented, as the [panel kit](panel-kit.md) is. See [editor controls](controls.md) for the custom-element and bridge interfaces.

### Dashboard cards

`$app->dashboard->add(Provider::class)` appends a provider; `replace(...)` selects and orders the complete list. Plugins append through `Registrar::dashboardCard()`. Providers implement [DashboardCard](../src/Contract/DashboardCard.php) and return a nullable [Card](../src/Panel/Dashboard/Card.php). Class names are autowired per dashboard request; `null` omits the card. Labels and notes are text, integer values are locale-formatted, strings are display-ready, and optional URLs are panel-internal.

`panel.dashboard => false` removes the dashboard entry and directs panel home to the first collection, or media when there is none.

### Inspector sections

A site or a plugin can add a section to the status tab of the node editor's inspector, below the node's status, for state that belongs to a node but not to its content. A section implements [InspectorSection](../src/Contract/InspectorSection.php) and is registered with `$app->inspector->add()` or through `Registrar::inspectorSection()`:

```php
use Cosray\Config;
use Cosray\Contract\InspectorSection;
use Cosray\Node\Wrapper;
use Cosray\Panel\Inspector\Section;

final class StockSection implements InspectorSection
{
    public function __construct(
        private readonly Config $config,
        private readonly Stock $stock,
    ) {}

    public function section(Wrapper $node): ?Section
    {
        if ($node->meta->type->handle !== 'product') {
            return null;
        }

        return new Section(
            key: 'acme-shop',
            title: __('acme-shop:stock'),
            template: 'acme-shop:stock',
            context: ['count' => $this->stock->of($node->meta->uid)],
            action: "{$this->config->panel->path}/acme-shop/stock/{$node->meta->uid}",
        );
    }
}

$cms->inspectorSection(StockSection::class);
```

Class names are built per request with the constructor arguments nodes get, such as `Context`, `Cms`, `Config` and the `Database`. The node is the stored one as the editor shows it, with its working copy; a node that is being created has no sections. `null` leaves the section out. Decide that by what stays the same while the editor is open, like the node's type or the user's permissions: the editor draws each section again after every save and switch, so its content follows the node, but it never removes one.

The [Section](../src/Panel/Inspector/Section.php) names a `key`, unique among the sections, like the plugin's id; the heading; and the panel template of the content with its context. The editor's form wraps the inspector, and forms cannot nest, so a section's controls never join the editor's save. With an `action`, the editor keeps a form of the section's own outside its form, which posts there with the CSRF field, and the template's controls join it through the `form` variable they receive:

```php
<div class="cms-kit-inspector-row">
    <input class="cms-input" type="number" name="count" value="<?= $count ?>" form="<?= $form ?>" aria-label="<?= __('acme-shop:stock') ?>" />
    <button class="cms-button cms-button-secondary cms-button-small" type="submit" form="<?= $form ?>"><?= __('acme-shop:set') ?></button>
</div>
```

Such controls act at once, like the editor's own publication switch, and do not mark the node as edited; the heading of a section with an action says so. The action is a [panel route](#panel-pages-and-navigation) of the plugin, whose template answers with the section drawn again and, where it helps, a toast:

```php
<?php $this->include('kit/inspector-section', ['section' => $section, 'oob' => true]) ?>
<?php $this->include('component/toast', ['kind' => 'success', 'message' => __('acme-shop:stock-set')]) ?>
```

The [panel kit](panel-kit.md#inspector-sections) lists the classes for a section's content.

### Node listeners

A node listener hears of every write to a node, through the panel, the API or PHP code alike. It implements [NodeListener](../src/Contract/NodeListener.php) and is registered by a site with `$app->nodeListener()` or by a plugin through `Registrar::nodeListener()`:

```php
use Cosray\Contract\NodeListener;
use Cosray\Node\Write;
use Cosray\Queue;

final class PurgeCache implements NodeListener
{
    public function __construct(
        private readonly Queue $queue,
    ) {}

    public function written(Write $write): void
    {
        if ($write->has('published', 'unpublished', 'applied', 'saved', 'deleted')) {
            $this->queue->push(PurgePage::class, ['uid' => $write->uid]);
        }
    }
}

$app->nodeListener(PurgeCache::class);
```

A [Write](../src/Node/Write.php) names the node's `uid` and `type`, the `actor`, the time `at` and the `actions` the write recorded as [events](content.md#history), such as `applied` and `published` for a working copy that went live. `has()` asks for any of several actions, and `data()` returns what an action recorded besides, like the parents of a move or the cause its caller gave.

Listeners run inside the write's transaction, after its events are recorded, in the order they were registered. What a listener writes or queues is committed or rolled back with the write, and an exception fails the write, so keep a listener quick and push a [job](queue.md) for slow work. A write that changes nothing reaches no listener. A class name is built once per request, job run or command, with the constructor arguments nodes get; a class the container knows is taken from there. [Webhooks](api.md#webhooks) are a listener Cosray registers itself.

## Icons

Icon ids such as `#[Icon('bi:check')]` resolve through the icon providers in order. By default these are the site's own SVG files below `icons.local.paths`, where `bi:check` means `bi/check.svg` and an unprefixed `logo` means `logo.svg`, then the [Iconify API](https://iconify.design/) for prefixed ids. `$app->icons()` adds a provider before them or, with `replace: true`, replaces both. Iconify responses are cached below `{path.public}{path.cache}/icons/`. Pages include icon markup inline, so icons can use `currentColor`.

Local files are used as they are. Iconify markup is sanitized like an [SVG upload](media.md#svg-uploads), and because inline SVG shares the page's CSS and layout, it also loses all comments (legal comments included), `<style>` elements, the root element's `style`, `transform`, `overflow`, and `filter`, and animations of the root element. This does not affect icons drawn with paths and `currentColor` or animated with SVG animation elements such as `<animate>` and `<animateTransform>`. Icons that are styled or animated by a stylesheet, for example `@keyframes` in a `<style>` element as some spinner sets use, lose that styling: they render static or with default fills.

A plugin ships icons with `$cms->icons(__DIR__ . '/../icons')`: `acme-shop:truck` is `truck.svg` in that directory. A site's own file `acme-shop/truck.svg` below a local icon path comes first, and plugin icons resolve before Iconify, which also has dashed set names such as `simple-icons`.

Prefer static icons, and for animation choose icons that use SVG animation elements; check an animated icon where it is shown before relying on it. To use an icon that needs its stylesheet, review it and place a copy below a local icon path.

Iconify responses carry no license notices. Check the license of each icon set you use; some require attribution on the site.

## Panel assets and theming

The panel's browser files ship with the package as they are, so `composer install` or `update` is the whole installation. PHP serves `panel/src`, `panel/styles`, `panel/icons` and the vendored third-party modules in `panel/modules` under `{panel.path}/assets/{revision}/`, plus an icon sprite it builds from `panel/icons`, below `composer/` the modules other Composer packages ship, such as verba's translation runtime from `celema/verba`, and below `plugins/{id}/` the assets of plugins. The revision derives from the commits Composer installed for Cosray, those packages and the plugins' packages, or from a hash of the version for a package without one, and from the asset files of a plugin without a package. In debug mode and in a Git working copy, such as a symlinked path repository, the revision is instead a hash over the files themselves, so every edit gets new URLs. Responses for the current revision may be cached for a year (`immutable`); for any other they are revalidated through an ETag.

A first visit loads the modules one by one: about 90 files for the dashboard and 130 for an editor with rich text and code, around 200 and 700 KB compressed. Make sure the web server compresses `text/javascript` and `text/css` responses (gzip, Brotli or zstd) and speaks HTTP/2; uncompressed, the same visits move several times the bytes. Later visits load nothing but the page.

### Publishing for static delivery

Optionally let the web server deliver the panel files directly:

```bash
php run panel:publish
```

The command copies the browser files from the installed packages and generates the icon sprite at `{path.public}{panel.path}/assets/{revision}/` (by default, `public/panel/assets/{revision}/`). It includes the Composer-provided browser modules and the assets of registered plugins, but not PHP views or package configuration. No new settings or URL changes are needed; `app.url_prefix` does not add a filesystem directory.

Run it after `composer install` or `composer update` in deployment, using the same configuration and package files as the web application. A complete revision is published at once. Repeating the command verifies an existing publication without overwriting it; a conflicting or incomplete directory causes an error. Older revisions and unrelated application files are left untouched. Remove obsolete revision directories separately when they are no longer needed for open browser tabs or rollbacks. Publishing is intended for production installs; during development, PHP serves edits without needing another publish step.

Configure the web server to serve existing **files** directly and route other requests, including the panel's directory paths, to PHP. Disable directory listings, serve `.js` as `text/javascript`, and configure compression and `Cache-Control: public, max-age=31536000, immutable` for the published revision files. Keep the ordinary PHP fallback: it still serves unpublished revisions and the unversioned plugin asset route. No server configuration files are generated.

`panel.theme` accepts a stylesheet URL or a list of URLs. [Panel styles](panel-styles.md) explains the cascade and current theming approach. [Panel development](../panel/README.md) covers the panel's checks and the live styleguide.

## Users, roles and permissions

Roles and allow entries live in code; the database stores each user's role names. [Security\Policy](../src/Security/Policy.php) resolves principals (`everyone`, `authenticated`, `user:{uid}`, `type:{handle}`, `role:{name}`) against permissions. Grants from multiple roles combine; unknown roles grant nothing.

```php
$app->role('shop', 'Shop manager');
$app->allow('role:shop', 'panel', 'edit-orders');
$policy->permits($user, 'edit-orders');
```

Routes use `#[Permission(...)]` middleware; panel controllers can call `$this->permits(...)`. The masthead's gear opens the system area for every panel user; its rail lists only the entries the user may open, Users with `edit-users`, [System info](#system-info) with `view-system-info`, which only superusers hold by default, and everyone's profile. The Users screens need `edit-users`. Their write policy prevents editors from granting access beyond their own, changing their own roles/state, or removing the last active superuser. Password changes end other sessions and remembered logins. The profile page allows self-service account changes, with the current password required for a new one.

Users have an email login and optional username without `@`. A subclass of [Cosray\User](../src/User.php), registered through `$app->user()`, adds fields like a node class. `#[Roles(...)]` limits assignable roles; `#[Roles]` allows none. Fields are neutral-locale values stored under `content` in `users.data`, without working copies, and hydrate on demand through [User\Fields](../src/User/Fields.php). See [User\Types](../src/User/Types.php) and its tests for type registration and default-type replacement.

The panel speaks each language for which both its catalogs, `cosray` and `panel`, ship a file. It takes the language a user saved in the profile or the account menu, then the one chosen on the sign-in pages, kept in the cookie `{app.name}_panel_locale`, then `panel.locale`, then the browser's `Accept-Language`, then English. The sign-in pages offer the choice once there is more than one language.

Dates, times, and numbers in the panel follow the panel language and a region. The region is the regional format a user chose in the profile, else the browser's first `Accept-Language` tag in that language, so `en-GB` gets day-first dates and a 24-hour clock in the English panel. Without one, `panel.region` applies, like `'panel.region' => 'GB'`; without that, the language's own default, which is the United States for English. Timestamps the system records, like when an entry changed, show in the user's chosen time zone, else in `app.timezone`; the profile offers the browser's time zone when it differs. Date and time fields keep their own `meta.timezone`, in the editor and in listing columns.

### System info

The system info page, `{panel.path}/system-info`, shows what the web server's PHP offers Cosray: the versions, the required and optional extensions, the upload and memory limits against `upload.maxsize` and `upload.maxsize.video`, the database server, migrations that `db:migrations --apply` would still run, [jobs](queue.md) that failed for good or have waited more than 30 minutes past their due time, and the writable directories. Problems and warnings come first, with what to change, and a button copies the whole report as plain text, for a support request. Its PHP tab lists every ini directive and loaded extension, and replaces `phpinfo()`, which would also show environment variables and the request's cookies; values that look like passwords are masked. A site lets admins see it with `$app->allow('role:admin', 'view-system-info')`. [SystemInfo](../src/SystemInfo.php) builds the report as plain data, for the page and `system:info`.

### User commands

Users can also be managed from the console:

```bash
php run user:list --type=user --search=keller
php run user:create --email=editor@example.com --name="Mia Keller" --role=editor
php run user:password editor@example.com
php run user:login-link editor@example.com --next=/node/abc
php run user:deactivate editor@example.com
php run user:activate editor@example.com
```

`user:list` lists every user, deactivated ones included, with type, roles and state; `--type` takes a type's handle, `--search` matches email, username and name.

`user:create` creates an active user, of the default type unless `--type` names another, and grants each `--role`. In a terminal it asks for the email address, the name and the roles the options leave out; the password it always asks for, twice and hidden, so it never lands in the shell history. Piped input answers the prompts in a script. The first superuser of a new site comes from `user:create --role=superuser`. Fields of a user type are left empty; fill them in the panel.

`user:password` sets a new password the same way, for deactivated users too, and like any password change it ends the user's sessions and remembered logins.

`user:login-link` prints a link that signs an active user with panel access in once, within five minutes, through the same [handoff](api.md#embedded-editing) an embedding application uses; `--next` names the panel page to open. It gets in without knowing or changing a password, for a locked-out administrator or a quick look as someone. The link is built from `app.url`; without it, the command warns and prints the path alone. Creating one is logged as `notice`, as are the other changes these commands make.

`user:deactivate` keeps a user from signing in and ends their sessions, remembered logins and API tokens with the account, as the panel does; it refuses the last active superuser. `user:activate` lets them in again.

### Passwords

The user form, the profile, the [password reset](#password-reset) and the [user commands](#user-commands) accept passwords of 12 to 512 characters that are not easy to guess, as [User\PasswordPolicy](../src/User/PasswordPolicy.php) judges without a word list. Runs like `1111`, sequences along the alphabet, the digits or a keyboard row like `abcd`, `4321` or `qwertz`, and parts repeated from earlier in the password each count as one character, and so do parts of the account's name, username or email address and of `app.name`, `app.title` and the host of `app.url`; at least 10 characters must remain. So `Password1234` and `hallohallo12` fail, while a passphrase passes.

Common words with a number, like `Sommer2026!!`, pass these checks. To refuse passwords known from data breaches, set `password.breach_check` or `PASSWORD_BREACH_CHECK=true`: Cosray then asks the [Pwned Passwords API](https://haveibeenpwned.com/API/v3#PwnedPasswords) of Have I Been Pwned whether a new password appears in its breach data. When the service does not answer within five seconds, the password is accepted and a `warning` is logged.

The check sends nothing that identifies a person. The server makes the request, only when a password is set, never at sign-in, so the client's address does not reach the service. The request holds the first five characters of the password's SHA-1, which hundreds of known hashes and countless other passwords share, and no password, account data or other part of the hash. The service answers with the rest of every hash under that prefix, padded with decoys, and the server compares them. The service sees only the site server's address and the prefix. The check is off by default because the request goes to a third party, run from Australia and served through Cloudflare; sites that turn it on can mention it in their record of processing activities and the privacy notice for panel users.

### CSRF protection

Every request to the panel other than `GET`, `HEAD` and `OPTIONS`, including sign-in, sign-out and the password reset, and every upload to `/media/{mediatype}` must carry the session's CSRF token, or [VerifyCsrf](../src/Middleware/VerifyCsrf.php) refuses it. Panel pages carry the token in `<meta name="csrf-token">`, and the bridge's `system()` returns it as `csrfToken`. The panel's scripts send it as the `X-CSRF-Token` header, and every form in the panel views that posts includes `component/csrf`, a hidden `_token` field, so it also works without the scripts. Scripts of plugins and element controls that send such requests themselves add the header. A JSON body needs the header, as the check does not read the field there.

A session keeps its token until it ends or its user signs in, so tabs of the same session work side by side. A refused request is logged as `warning`. When the panel's scripts sent it, the answer is `403` with `HX-Trigger: cosray:csrf-expired` and nothing to swap, and the panel offers to reload the page in a dialog, leaving unsaved edits in place to copy. A plain form submit is redirected to `{panel.path}/expired`, which explains that the page was open for too long and links back to it.

Requests with `Authorization: Bearer <token>` skip the check: such a request is authenticated by the [API token](#api-tokens) alone, never by a session or remember-me cookie, so it carries no credential a browser sends unasked. A plugin route outside the panel group that changes data over the panel session needs the same check; add `VerifyCsrf` after the session middleware.

### Sign-in limits

The panel counts failed sign-ins per client address, in windows of 15 minutes that start with the first failure. After 5 failures for one login, or 30 for any logins, it refuses further sign-ins from that address with `429 Too Many Requests` and a `Retry-After` header until the window ends, even with the right password. A successful sign-in clears the count of its login and does not count for the address. A failed one-time token [handoff](api.md#embedded-editing) counts for the address too. The counters live in the unlogged `rate_limits` table under an HMAC with `app.secret`, so it holds no addresses or logins. Behind a reverse proxy, list it in [`app.trusted_proxies`](#filesystem-directories-and-url-paths), or all visitors share the proxy's address and its limit.

### Password reset

With a [mail transport](mail.md) and `app.url` configured, the login page links to a form where panel users who forgot their password request a link by mail. The link works once and for an hour, a user gets at most one every five minutes, and an address may ask for at most five every 15 minutes. The request only queues a [job](queue.md) that looks the login up and sends the mail, so the form answers the same, and does the same work, whether or not the login belongs to an account; by default, the job runs once the response is out. The mail is in the user's panel language, or else in that of the request. Setting the new password ends the user's other sessions and remembered logins like any password change. Requests and resets are logged as `notice`. Without mail or `app.url`, there is no link and the pages answer 404.

The mail comes from the template `cosray/password-reset`. A file `cosray/password-reset.php` in the `mail.views` directory replaces it; it receives `name`, `site` (the `app.title`) and `url`.

### Node permissions

A node type maps the actions `read`, `create`, `change` and `delete` to permissions. By default, routable types read with `read-pages`, which the default policy grants to `everyone`, and other types with `read-nodes`, which editors, admins and superusers hold; every write needs `edit-nodes`. `read` covers published, undeleted nodes, while any other state takes the type's `change` permission. A record is never published, so only `change` reads it.

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

# Cosray CMS

<!-- prettier-ignore-start -->
[![ci](https://codefloe.com/cosray/cms/badges/workflows/ci.yml/badge.svg?style=flat&logo=forgejo&logoColor=white&label=ci)](https://codefloe.com/cosray/cms/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celema.dev%2Fcosray%2Fcms%2Fcode%2Fbadge.json)](https://cov.celema.dev/cosray/cms/code)
[![REUSE status](https://api.reuse.software/badge/codefloe.com/cosray/cms)](https://api.reuse.software/info/codefloe.com/cosray/cms)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSES/MIT.txt)
<!-- prettier-ignore-end -->

Cosray is a PHP content management framework with code-first content models, PostgreSQL-backed storage, and an admin panel for editors.

> [!WARNING] Cosray has not had its first public release. Its API, data model, and panel design are still changing. These documents explain the current implementation, not a stability promise or a fixed product roadmap.

## Starting an application

`Cosray\App` creates the configuration and CMS bootstrap, installs the default error handler, and registers the CMS catchall route when `run()` is called. Application-defined node and collection classes are registered before that call:

```php
use Cosray\App;
use Cosray\Locales;

$app = App::create(dirname(__DIR__), [
    'app.name' => 'mycms',
]);

$locales = new Locales();
$locales->add('en', title: 'English', pgDict: 'english');
$app->load($locales);

$app->section('Content')->collection(\App\Cms\Collection\Pages::class);
$app->node(\App\Cms\Node\HomePage::class);

$app->run();
```

See [application setup](docs/application.md) for environment loading, console commands, panel installation, plugins, and users; [content models](docs/content.md) covers nodes, routes, queries, and rendering.

## Requirements

- PHP `^8.5` and Composer 2. Required extensions are declared in [composer.json](composer.json); run `composer check-platform-reqs` after installation.
- PostgreSQL 17 or newer with `btree_gist`, `btree_gin`, and `unaccent`. Structured field sorting uses PostgreSQL 17's SQL/JSON scalar extraction. CI uses PostgreSQL 17. The migration role needs permission to create the required extensions, or an administrator must provision them.
- A web server routing requests to the public PHP entrypoint while serving existing public files directly.
- Node.js and pnpm for building the panel, with versions declared in [panel/package.json](panel/package.json). The transitional legacy richtext migration also needs Node.js, but ordinary CMS requests do not.

## Documentation

Read the topic relevant to the task rather than loading every reference. When documentation and implementation disagree, inspect the source and tests before relying on the documented behavior. A current limitation is not a prohibition on exploring a different design.

These links describe a source checkout. Distribution archives omit development documentation and tests; for an installed package, consult the [repository](https://codeberg.org/cosray/cms) at the source reference recorded in its Composer lock file, not necessarily the latest branch.

| Topic | Reference |
| --- | --- |
| Configuration, console, plugins, users, panel integration | [Application setup](docs/application.md) |
| Nodes, embedded fields, routes, queries, menus, working copies | [Content models](docs/content.md) |
| Assets, image sizes, imports, reference indexes | [Media](docs/media.md) |
| Field descriptors, form transport, custom elements | [Editor controls](docs/controls.md) |
| Block types, translation, storage, frontend rendering | [Blocks](docs/blocks.md) |
| Richtext storage and validation | [Richtext format](docs/richtext-format.md) |
| Search selection, queries, safe snippets, deployment | [Full-text search](docs/fulltext.md) |
| CSS conventions and the live styleguide | [Panel styles](docs/panel-styles.md) |
| Current shortcuts and keyboard considerations | [Panel keyboard](docs/panel-keyboard.md) |
| PHP tests and isolated database setup | [Testing](tests/README.md) |
| Panel development and tests | [Panel](panel/README.md) |
| Shared PHP/TypeScript behavior fixtures | [Contract fixtures](contract/README.md) |

There is no rolling changelog before the first public release. Git history and tags retain development history; migration requirements belong with the affected feature.

## Source map

- [src/App.php](src/App.php), [src/Bootstrap.php](src/Bootstrap.php): application integration.
- [src/Config/Defaults.php](src/Config/Defaults.php), [src/Config.php](src/Config.php): settings and typed access.
- [src/Node/](src/Node/), [src/Field/](src/Field/), [src/Value/](src/Value/): schema, persistence, and template-facing values.
- [src/Routes.php](src/Routes.php), [src/Controller/](src/Controller/): HTTP endpoints and access checks.
- [panel/views/](panel/views/), [panel/src/](panel/src/), [panel/styles/](panel/styles/): Boiler SSR views, htmx behaviors, custom elements, and CSS.
- [db/migrations/](db/migrations/), [db/sql/](db/sql/): schema evolution and named queries.
- [tests/](tests/), [panel/tests/](panel/tests/): executable examples and regression coverage.

## License

Cosray is licensed under [MIT](LICENSES/MIT.txt). Bundled third-party files retain their respective licenses; see [REUSE.toml](REUSE.toml).

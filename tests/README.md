# Testing Cosray

The PHP suite uses PHPUnit with unit, PostgreSQL integration, and in-process HTTP tests. Browser-side panel behavior has a separate [Vitest suite](../panel/README.md). See [phpunit.xml.dist](../phpunit.xml.dist), [composer.json](../composer.json), and the [CI workflow](../.forgejo/workflows/ci.yml) for current configuration.

## Commands

Run from the repository root:

```bash
composer test
composer lint
composer coverage
composer docs:lint
```

`composer ci` runs PHP linting, coverage, and the license check; `composer ci:full` adds documentation linting. Coverage needs Xdebug and writes HTML to `.coverage/html/index.html`.

For a narrower run:

```bash
vendor/bin/phpunit --no-coverage --testsuite unit
vendor/bin/phpunit --no-coverage --testsuite integration
vendor/bin/phpunit --no-coverage --testsuite end2end
vendor/bin/phpunit --no-coverage tests/Unit/RoutePathGeneratorTest.php
vendor/bin/phpunit --no-coverage --filter Fulltext
```

## Isolated database setup

Use an explicitly authorized test database, never an application database. Defaults are host `localhost`, database/user/password `cosray`; override them with `COSRAY_DB_HOST`, `COSRAY_DB_NAME`, `COSRAY_DB_USER`, and `COSRAY_DB_PASSWORD`. Keep credentials local and use the same connection settings for migrations and tests. PostgreSQL CLI tools can use `.pgpass`; that does not replace the PHP test connection settings.

With a suitable role and permission to create the required extensions, initialize an empty isolated database:

```bash
export COSRAY_DB_HOST=localhost
export COSRAY_DB_NAME=cosray_test
export COSRAY_DB_USER=cosray

createdb --host "$COSRAY_DB_HOST" --username "$COSRAY_DB_USER" "$COSRAY_DB_NAME"
php run db:migrations --namespace=install --apply
php run db:migrations --apply
```

The install namespace creates the current schema and records included updates. An already initialized test database normally needs only the update command. Schema checks report when setup is missing or outdated.

`php run recreate-db` is destructive: it terminates connections and drops/recreates the selected database. Use it only for an explicitly approved isolated reset, never as an automatic response to a failing test.

## Test isolation

[IntegrationTestCase](IntegrationTestCase.php) checks the schema and wraps ordinary database tests in rollback transactions. [End2EndTestCase](End2EndTestCase.php) shares the in-process application's database instance with the harness so HTTP writes join the same transaction. These HTTP tests do not exercise a real browser.

[FulltextConcurrencyTest](Integration/FulltextConcurrencyTest.php) is an exception: a second PHP process needs committed, uniquely named fixtures, removed in `finally`. It uses `proc_open` and observes PostgreSQL lock waits. The full-text suite also requires `unaccent` and the schema from migration `000000-000036` or a current install. See [search maintenance](../docs/fulltext.md#synchronization-and-maintenance).

## Writing tests

Use the existing test nearest the behavior as an example rather than copying a generic template:

- [Unit tests](Unit/) cover isolated schema, parsing, values, and rendering behavior.
- [Integration tests](Integration/) exercise real database queries, writes, and migrations.
- [Panel save tests](End2End/PanelEditorSaveTest.php) show authentication, complete editor payloads, validation, and persisted results. Saves need `_complete: "1"` just like the real form.
- [Fixtures](Fixtures/) and the base test classes provide schemas, SQL data, and creation helpers.
- [Contract fixtures](../contract/README.md) keep shared PHP/TypeScript semantics aligned.

Test observable behavior and meaningful failure modes. Avoid locking in incidental markup or removed implementation details merely to prove a particular diff. For intentional UI changes, retain checks for submission, focus, accessible names, and data preservation while reassessing presentation assertions.

If a run fails before tests start, check the selected database, schema state, PHP extensions, and configured runtime versions before changing tests or resetting anything.

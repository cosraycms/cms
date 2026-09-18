# Cosray CMS Testing Guide

This guide explains how to set up and run tests for the Cosray CMS project.

## Test Architecture

The test suite combines three types of tests:

- **Unit Tests**: Fast tests for isolated components (lexer, parser, utilities, field capabilities)
- **Integration Tests**: Tests that interact with a real PostgreSQL database directly
- **End-to-End (E2E) Tests**: Tests that exercise the full HTTP request/response cycle through the application

### Key Principles

1. **No Mocks in Integration Tests**: Integration tests use real database connections and actual data
2. **Transaction Isolation for Integration Tests**: Each integration test runs in a transaction that's rolled back after completion
3. **Transaction Isolation for E2E Tests**: E2E tests share the application's database instance with the test harness so HTTP writes roll back with the test
4. **Fixture-Based**: Tests use SQL fixtures and helper methods for consistent test data
5. **Shared Setup**: Initialize an isolated database before running tests; the suite checks its schema once, then transactions isolate ordinary database tests. The full-text concurrency test uses committed, uniquely named fixtures and removes them in `finally`.

## Prerequisites

### 1. PostgreSQL Setup

The test suite requires a PostgreSQL database. Ensure PostgreSQL is installed and running:

```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Start if not running
sudo systemctl start postgresql
```

### 2. Database User

Create a PostgreSQL user and database for testing. The default connection uses host `localhost` and database/user/password `cosray`.

```bash
# Run on the PostgreSQL host or adapt -h/--host for your setup.
sudo -u postgres createuser --pwprompt --createdb cosray
createdb --user cosray --owner cosray cosray
```

Override the host or credentials with environment variables:

```bash
COSRAY_DB_HOST=<your-hostname> composer test
COSRAY_DB_HOST=<your-hostname> php run db:migrations --apply
```

### 3. Initialize Test Database

Use an explicitly authorized, isolated database, never an application database. Set the same connection variables for every migration and test command. Supply credentials locally through the existing test environment or `.pgpass` for PostgreSQL CLI tools.

```bash
export COSRAY_DB_HOST=localhost
export COSRAY_DB_NAME=cosray_test
export COSRAY_DB_USER=cosray

createdb --host "$COSRAY_DB_HOST" --username "$COSRAY_DB_USER" "$COSRAY_DB_NAME"
php run db:migrations --namespace=install --apply
php run db:migrations --apply
```

The install namespace creates the current schema and records included updates. An existing initialized test database normally only needs `php run db:migrations --apply`. `recreate-db` is destructive: it terminates connections, drops the target database and creates it again. Use it only for an explicitly approved reset of an isolated database.

## Running Tests

### Run All Tests

```bash
composer test
```

## How to Resume

- Run `composer test` to verify the suite.
- If the initialized database is out of date, run `php run db:migrations --apply` with the same isolated connection variables.
- Use `composer coverage` when you need updated coverage numbers.

### Full-text search validation

```bash
vendor/bin/phpunit --no-coverage --filter Fulltext
composer ci:full
```

The full-text tests require migration `000000-000036` (or the current fresh-install schema) and PostgreSQL's `unaccent` extension:

- `FulltextBuilderTest`: database-free selection, inheritance/exclusion, effective locales, typed rows, embeds, conditions, rich-text boundaries and title-provider precedence.
- `FulltextIndexTest`: configurations, stemming/stopwords, original-spelling accent highlights, weights/phrases, replacement, oversized vectors and fresh-install parity.
- `FulltextStoreTest`: live writes and working-copy transitions, rollback, rebuild equivalence, missing titles, removed selections and failure reporting.
- `FulltextConcurrencyTest`: a separate PHP process waits for an uncommitted live writer, then rebuilds its committed content. It uses `proc_open`, observes lock waits through `pg_stat_activity`, and cleans up its own committed fixtures.
- `FulltextFinderTest`: result eligibility, active/fallback URLs, filter/count/pagination composition, web-search syntax and query-local metadata.
- `FulltextSnippetTest`: escaped highlights and deliberate safe rendering through Boiler.

Configuration provisioning and install-parity tests create temporary schema objects inside the test transaction. No application schema opt-ins or application database rebuilds are needed to run this suite. Operational guidance is in [Full-text search](../docs/fulltext.md).

### Run Specific Test Suite

```bash
# Run a specific test file
vendor/bin/phpunit tests/Integration/NodeTest.php

# Run a specific test method
vendor/bin/phpunit --filter testCreateAndRetrieveNode tests/Integration/NodeTest.php
```

### Run Only Unit Tests

```bash
vendor/bin/phpunit --testsuite unit
```

### Run Only End-to-End Tests

```bash
vendor/bin/phpunit --testsuite end2end
```

### Run Tests by Group (if tagged)

```bash
# Run tests marked with @group integration
vendor/bin/phpunit --group integration
```

_Note: E2E tests are in the `end2end` test suite by configuration in phpunit.xml.dist_

### Generate Coverage Report

```bash
composer coverage
```

Open `coverage/index.html` in your browser to view the report.

## Test Structure

### Directory Layout

```text
tests/
├── TestCase.php                  # Base class for all tests
├── IntegrationTestCase.php       # Base class for integration tests
├── End2EndTestCase.php           # Base class for end-to-end tests
├── Fixtures/
│   ├── data/                     # SQL fixture files
│   │   ├── basic-types.sql       # Common content types
│   │   ├── test-users.sql        # Test user accounts
│   │   └── sample-nodes.sql      # Sample content nodes
│   ├── Node/                     # Test node classes
│   │   ├── TestDocument.php
│   │   ├── TestMediaDocument.php
│   │   └── TestPage.php
│   └── templates/                # Template files for E2E tests
├── Integration/                  # Integration tests
│   ├── *Test.php                 # Direct database tests
├── End2End/                      # End-to-end tests
│   ├── NodeCrudTest.php          # Node CRUD API tests
│   └── RoutingTest.php           # Routing and rendering tests
└── *Test.php                     # Unit test files
```

### Base Test Classes

#### `TestCase`

Base class for all tests, provides:

- **Database helpers**: `db()`, `config()`, `registry()`, `factory()`
- **HTTP helpers**: `request()`, `psrRequest()`, `setMethod()`, `setRequestUri()`
- **Utility helpers**: `fullTrim()`

#### `IntegrationTestCase`

Extends `TestCase` for integration tests, provides:

- **Automatic transaction isolation**: Sets `$useTransactions = true` (each test runs in a transaction that rolls back)
- **Database initialization**: Checks schema exists on first test class run
- **Fixture loading**: `loadFixtures(...$fixtures)`
- **Test data creation**: `createTestType()`, `createTestNode()`, `createTestUser()`, `createTestPath()`
- **Context creation**: `createContext()`
- **Cms creation**: `createCms()`

#### `End2EndTestCase`

Extends `IntegrationTestCase` for end-to-end HTTP tests, provides:

- **Application setup**: `createApp()` initializes the full CMS application
- **Authentication helpers**:
  - `createAuthenticatedUser(role)` - Creates a user with auth token
  - `authenticateAs(role)` - Sets default auth token for subsequent requests
- **HTTP request helpers**: `makeRequest(method, uri, options)` - Simulates HTTP requests through the app
- **Response assertions**:
  - `assertResponseOk(response)` - Assert status code is 2xx
  - `assertResponseStatus(expected, response)` - Assert specific status code
  - `getJsonResponse(response)` - Decode response body as JSON
- **Shared transactions**: Uses the application's database instance so HTTP writes roll back with the test

## Writing Tests

### Unit Test Example

```php
<?php

namespace Cosray\Tests;

use Cosray\Tests\TestCase;

final class PasswordTest extends TestCase
{
    public function testPasswordHashing(): void
    {
        $password = 'secret123';
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $this->assertTrue(password_verify($password, $hash));
    }
}
```

### Integration Test Example

```php
<?php

namespace Cosray\Tests;

use Cosray\Tests\IntegrationTestCase;

final class MyIntegrationTest extends IntegrationTestCase
{
    public function testNodeCreation(): void
    {
        $typeId = $this->createTestType('my-test-type', 'page');

        $nodeId = $this->createTestNode([
            'type' => $typeId,
            'content' => ['title' => ['type' => 'text', 'value' => ['en' => 'Test']]],
        ]);

        $node = $this->db()->execute(
            'SELECT * FROM cms.nodes WHERE node = :id',
            ['id' => $nodeId]
        )->one();

        $this->assertNotNull($node);
        $this->assertEquals($typeId, $node['type']);
    }
}
```

### End-to-End Test Example

```php
<?php

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

final class NodeSaveTest extends End2EndTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Load test data fixtures
        $this->loadFixtures('basic-types', 'sample-nodes');
    }

    public function testSaveNode(): void
    {
        // Authenticate before making panel requests
        $this->authenticateAs('editor');

        $type = $this->db()->execute(
            "SELECT type FROM cms.types WHERE handle = 'test-article'",
        )->one();
        $this->createTestNode([
            'uid' => 'my-node',
            'type' => (int) $type['type'],
            'content' => [
                'title' => ['type' => 'text', 'value' => ['en' => 'My Node']],
            ],
        ]);

        // Make an HTTP request against the panel editor endpoint
        $response = $this->makeRequest('POST', '/cp/node/my-node', [
            'headers' => ['HX-Request' => 'true'],
            'body' => [
                'publish' => '1',
                'content' => ['title' => ['value' => ['en' => 'Updated Node']]],
            ],
        ]);

        // Assert response and persisted state
        $this->assertResponseOk($response);
        $row = $this->db()->execute(
            "SELECT content->'title'->'value'->>'en' AS title FROM cms.nodes WHERE uid = 'my-node'",
        )->one();
        $this->assertSame('Updated Node', $row['title']);
    }
}
```

### Using Fixtures

```php
public function testWithFixtures(): void
{
    // Load SQL fixtures
    $this->loadFixtures('basic-types', 'sample-nodes');

    // Use Cms to query fixture data
    $cms = $this->createCms();
    $nodes = $cms->nodes()->types('test-page')->get();

    $this->assertNotEmpty($nodes);
}
```

## Test Database Workflow

### Integration Tests - Transaction Isolation

1. **First test class runs** → Database schema is checked (one-time)
2. **Test begins** → Transaction starts (`BEGIN`)
3. **Test executes** → All database operations happen in transaction
4. **Test completes** → Transaction rolls back (`ROLLBACK`)
5. **Next test begins** → Clean database state (transaction starts)

This ensures:

- ✅ Each test has a clean database state
- ✅ No test data persists between tests
- ✅ Tests can run in any order
- ✅ Fast execution (no database recreation)

### End-to-End Tests - Transaction Isolation

E2E tests adopt the database instance created by the in-process application and start the test transaction on that instance:

1. **Application starts** → Bootstrap creates and registers its database
2. **Test begins** → The test harness starts a transaction on that database
3. **HTTP requests run** → Application and test helpers share the transaction
4. **Test completes** → The transaction rolls back

PostgreSQL also rolls back the open transaction if an interrupted test process disconnects, so a crashed run does not leave types, nodes, users, or assets behind.

### When to Recreate the Database

Apply new migrations to an initialized test database with `php run db:migrations --apply`. A fresh start or an incompatible local schema edit may require an explicitly approved reset of the isolated database:

```bash
php run recreate-db
php run db:migrations --namespace=install --apply
php run db:migrations --apply
```

## Troubleshooting

### "Test database not initialized"

**Error:**

```text
RuntimeException: Test database not initialized. Run: ./run recreate-db && ./run migrate --apply
```

**Solution:** After checking the isolated connection variables, initialize the existing empty test database:

```bash
php run db:migrations --namespace=install --apply
php run db:migrations --apply
```

### "Migrations not applied"

**Error:**

```text
RuntimeException: Migrations not applied to test database. Run: ./run migrate --apply
```

**Solution:**

```bash
php run db:migrations --apply
```

### "Authentication failed"

**Error:**

```text
PDOException: SQLSTATE[28000] authentication failed for user "cosray"
```

**Solution:** Ensure the database user exists with the correct password `cosray` and with CREATEDB privilege. See above.

### "Permission denied to create database"

**Error:**

```text
PDOException: permission denied to create database
```

**Solution:** Grant CREATEDB privilege to the user:

```bash
sudo -u postgres psql -c "ALTER USER cosray CREATEDB;"
```

### Database Connection Configuration

Test database credentials default to:

```php
// Database: cosray
// User: cosray
// Password: cosray
// Host: localhost
```

Override them with environment variables:

- `COSRAY_DB_HOST`
- `COSRAY_DB_NAME`
- `COSRAY_DB_USER`
- `COSRAY_DB_PASSWORD`

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    env:
      COSRAY_DB_HOST: localhost
      COSRAY_DB_NAME: cosray
      COSRAY_DB_USER: cosray
      COSRAY_DB_PASSWORD: cosray

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: cosray
          POSTGRES_USER: cosray
          POSTGRES_PASSWORD: cosray
        options: >-
          --health-cmd pg_isready --health-interval 10s --health-timeout 5s --health-retries 5


        ports:
          - 5432:5432

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.5"
          extensions: pdo, pdo_pgsql, pgsql

      - name: Install dependencies
        run: composer install

      - name: Initialize test database
        run: |
          php run db:migrations --namespace=install --apply
          php run db:migrations --apply

      - name: Run tests
        run: composer test

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

## Best Practices

### DO

- ✅ Extend `IntegrationTestCase` for database tests
- ✅ Extend `End2EndTestCase` for HTTP tests
- ✅ Use `createTestType()`, `createTestNode()` helpers for test data
- ✅ Load fixtures in `setUp()` when needed across all test methods
- ✅ Use descriptive test names (`testFinderReturnsNodesOfSpecificType`, `testCreateNodeReturns201`)
- ✅ Follow Arrange-Act-Assert pattern
- ✅ Database cleanup is automatic through transaction rollbacks
- ✅ For E2E tests: Call `$this->authenticateAs('editor')` before making API requests
- ✅ For page nodes: Include `paths` data with URL paths for required locales
- ✅ For page nodes: Include all required schema fields (`uid`, `published`, `locked`, `hidden`)
- ✅ Make test data unique (use `uniqid()` for node UIDs and paths to avoid conflicts between test runs)

### DON'T

- ❌ Use mocks for database interactions in integration tests
- ❌ Rely on test execution order
- ❌ Share state between tests
- ❌ Create permanent test data outside of transactions (integration tests) or without tracking (E2E tests)
- ❌ Use the same type handle for multiple tests in the same class without unique suffixes
- ❌ Skip `paths` for page nodes (they're required for database validation)
- ❌ Forget to authenticate before making API requests to protected endpoints

## Performance

Expected test execution times:

- **Unit tests**: < 1 second
- **Integration tests**: 5-15 seconds (depending on fixture data)
- **Full test suite**: ~10-20 seconds

To optimize:

- Minimize fixture data (only load what's needed)
- Use helper methods instead of loading large SQL files
- Consider splitting large integration tests into smaller focused tests

## Future Enhancements

- [x] Add end-to-end tests (HTTP request/response cycle)
- [x] Add authentication integration tests (via E2E tests)
- [x] Add URL path resolution tests (via E2E routing tests)
- [ ] Tag tests with `@group integration` for filtering
- [x] Add full-text search integration tests
- [ ] Database seeder for realistic test data
- [ ] Parallel test execution
- [ ] API documentation generation from E2E tests
- [ ] Load testing for performance benchmarks

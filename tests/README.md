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
3. **No Transactions for E2E Tests**: E2E tests disable transactions because the CMS creates separate database connections that cannot see uncommitted transaction data
4. **Fixture-Based**: Tests use SQL fixtures and helper methods for consistent test data
5. **Hybrid Setup**: Database schema is initialized once per test run, then transactions provide isolation for integration tests

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
COSRAY_DB_HOST=<your-hostname> ./run migrate --apply
```

### 3. Initialize Test Database

Create and initialize the test database:

```bash
# Create the database
./run recreate-db

# Apply all migrations
./run migrate --apply
```

The `recreate-db` command:

- Terminates existing connections to the database
- Drops the database if it exists
- Creates a fresh database
- Sets the owner to `cosray`

## Running Tests

### Run All Tests

```bash
composer test
```

## How to Resume

- Run `composer test` to verify the suite.
- If the database is out of date, run `./run recreate-db && ./run migrate --apply`.
- Use `composer coverage` when you need updated coverage numbers.

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

```
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
- **Disabled transactions**: Sets `$useTransactions = false` because the CMS creates separate DB connections
- **Automatic cleanup**: Cleans up created test data including FK-referenced records

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
        $response = $this->makeRequest('POST', '/cp/collection/test-articles/my-node', [
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

### End-to-End Tests - No Transactions

E2E tests **disable transactions** because the CMS creates separate database connections:

1. **Test begins** → NO transaction (disabled in `End2EndTestCase`)
2. **Application runs** → Creates its own DB connection, inserts data
3. **Test completes** → Explicit cleanup: deletes created test data
4. **Next test begins** → Clean database state

The cleanup process handles foreign key constraints by deleting in proper order:

1. Delete FK-referenced records (history records, fulltext index, etc.)
2. Delete the main records
3. Delete created types

This prevents FK constraint violations during cleanup.

### When to Recreate the Database

Recreate the test database when:

- Migrations have been added or modified
- Database structure has changed
- Tests are failing due to schema issues
- You want a completely fresh start

```bash
./run recreate-db && ./run migrate --apply
```

## Troubleshooting

### "Test database not initialized"

**Error:**

```
RuntimeException: Test database not initialized. Run: ./run recreate-db && ./run migrate --apply
```

**Solution:**

```bash
./run recreate-db && ./run migrate --apply
```

### "Migrations not applied"

**Error:**

```
RuntimeException: Migrations not applied to test database. Run: ./run migrate --apply
```

**Solution:**

```bash
./run migrate --apply
```

### "Authentication failed"

**Error:**

```
PDOException: SQLSTATE[28000] authentication failed for user "cosray"
```

**Solution:** Ensure the database user exists with the correct password `cosray` and with CREATEDB privilege. See above.

### "Permission denied to create database"

**Error:**

```
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
                  php-version: '8.5'
                  extensions: pdo, pdo_pgsql, pgsql

            - name: Install dependencies
              run: composer install

            - name: Initialize test database
              run: |
                  ./run recreate-db
                  ./run migrate --apply

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
- ✅ Clean up is automatic (via transactions for integration, via explicit cleanup for E2E)
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
- [ ] Add full-text search integration tests
- [ ] Database seeder for realistic test data
- [ ] Parallel test execution
- [ ] API documentation generation from E2E tests
- [ ] Load testing for performance benchmarks

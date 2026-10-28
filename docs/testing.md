# Testing

`Cosray\Testing\TestCase` is a PHPUnit base class for a site's tests. Each test gets a new instance of the site's app. Everything the test and its requests write to the database is rolled back afterwards, and requests run no jobs after their response, so mails stay in the queue for the test to read and are never sent. See [Testing\TestCase](../src/Testing/TestCase.php).

## Setup

Install PHPUnit and autoload the tests:

```bash
composer require --dev phpunit/phpunit
```

```json
"autoload-dev": {
    "psr-4": {
        "App\\Tests\\": "tests"
    }
}
```

Tests need a database of their own with the site's schema. For a project from the application template, create an empty database and migrate it like the development database, with `DATABASE_URL` pointing at it:

```bash
createdb example_test
export DATABASE_URL="pgsql:host=localhost;dbname=example_test;user=example;password=example"
php run db:migrations --namespace=install --apply
php run db:migrations --apply
```

Tell PHPUnit to use it in `phpunit.xml.dist`, together with anything else tests must not take from `.env`. The app still loads `.env`, but a value set here wins, as Cosray reads `$_SERVER` first and `.env` never replaces a variable that is already set:

```xml
<phpunit bootstrap="vendor/autoload.php" executionOrder="random">
    <testsuites>
        <testsuite name="site">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <php>
        <server name="DATABASE_URL" value="pgsql:host=localhost;dbname=example_test;user=example;password=example"/>
        <server name="MAILER_DSN" value="null://null"/>
    </php>
</phpunit>
```

## Writing tests

A test creates the content it needs, makes requests and checks what the site answered, stored and queued:

```php
use App\Node\Contact;
use Cosray\Mailer;
use Cosray\Node\Writer;
use Cosray\Testing\TestCase;

final class ContactTest extends TestCase
{
    public function testAMessageIsMailedToTheOffice(): void
    {
        $writer = $this->service(Writer::class);
        $writer->create($writer->prepare(Contact::class, ['title' => ['en' => 'Contact']])->published());

        $response = $this->post('/contact', ['email' => 'ann@example.com', 'message' => 'Hello']);

        $this->assertSame(200, $response->getStatusCode());
        $mail = $this->service(Mailer::class)->queued()[0];
        $this->assertSame('office@example.com', $mail->getTo()[0]->getAddress());
    }
}
```

- `createApp()` returns what the project's `boot/app.php` returns. Override it for another layout, like `return require dirname(__DIR__) . '/app/app.php';`. It must create the app, not return one created earlier.
- `get()` and `post()` handle a request as the site would and return the response without sending it. A path goes to `http://localhost`; give an absolute URL for another host, like a locale's domain. Requests come from `127.0.0.1`. `post()` sends a form as a browser does. For anything else, build a request with `serverRequest()`, add to it, and pass it to `request()`.
- `service()` returns a service of the app as a job or a command gets it: without a request, in the default locale. Create content with [`Node\Writer`](content.md#programmatic-creation) and menus with `Cosray\Menus`, read what was stored with `Cms`, and what was queued with [`Mailer::queued()`](mail.md#testing) and [`Queue::pending()`](queue.md#testing).
- `$this->app` is the app of the running test.

## Isolation

Each test begins a transaction on the app's database connection and rolls it back when it ends, so the next test finds the database as it was. Sequences advance all the same, and what the site writes elsewhere, like uploaded files, stays.

The code under test works inside that transaction. A transaction it begins is a savepoint: its rollback undoes only its own work, and one it leaves open ends with the request, as it would on the server. A statement that fails outside the code's own transaction undoes the work of its request only, so the test can go on. Beginning a second transaction inside the first fails as it would without a test.

Sessions end with the test. Settings the app reads from `.env` still apply unless `phpunit.xml.dist` sets them, such as a provider's API token or the [breach check](application.md#passwords).

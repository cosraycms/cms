<?php

declare(strict_types=1);

namespace Cosray\Testing;

use Celema\Quma\Database as QumaDatabase;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Composer\InstalledVersions;
use Cosray\Actor;
use Cosray\App;
use Cosray\Console\Runtime;
use Cosray\Exception\RuntimeException;
use Cosray\Session;
use Cosray\User;
use Cosray\Users;
use Override;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Base class for a site's tests. Each test gets a new instance of the site's
 * app, whose database work happens in a transaction that is rolled back
 * after the test. Requests run no jobs after their response: queued mails
 * stay in the queue for the test to read and are never sent.
 *
 * @api
 */
abstract class TestCase extends PHPUnitTestCase
{
	protected App $app;
	private ?Database $db = null;
	private bool $signedIn = false;
	private ?Runtime $runtime = null;
	private ?Translator $previousTranslator = null;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->previousTranslator = Verba::translator();

		$this->app = Bootstrap::within($this->createApp(...))->boot();
		$db = $this->app->container()->get(QumaDatabase::class);

		if (!$db instanceof Database) {
			throw new RuntimeException('createApp() must create the app it returns, not reuse one.');
		}

		$db->isolate();
		$this->db = $db;
	}

	#[Override]
	protected function tearDown(): void
	{
		try {
			// Whatever the test activated, service() included, ends with it.
			if ($this->previousTranslator !== null) {
				Verba::activate($this->previousTranslator);
			} else {
				Verba::deactivate();
			}

			$this->previousTranslator = null;
			$this->runtime = null;
			$this->signedIn = false;
			$this->db?->discard();
			$this->db = null;
			self::endSession();
		} finally {
			parent::tearDown();
		}
	}

	/**
	 * Creates the site's app, anew for each test. By default it returns what
	 * the project's `boot/app.php` returns.
	 */
	protected function createApp(): App
	{
		$root = realpath(InstalledVersions::getRootPackage()['install_path']);
		$file = "{$root}/boot/app.php";

		if (!is_file($file)) {
			throw new RuntimeException("{$file} does not exist; override createApp() to create the site's app.");
		}

		$app = require $file;

		if (!$app instanceof App) {
			throw new RuntimeException("{$file} must return the site's Cosray\\App.");
		}

		return $app;
	}

	/**
	 * Handles the request as the site would, without sending the response.
	 * After actingAs(), a request that changes data carries the session's
	 * CSRF token, as the panel's pages send it.
	 */
	protected function request(Request $request): Response
	{
		if (
			$this->signedIn
			&& !in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
			&& !$request->hasHeader('X-CSRF-Token')
		) {
			$request = $request->withHeader('X-CSRF-Token', $this->csrfToken());
		}

		// PHPUnit lowers error_reporting while a test runs, and the app's error
		// handler ignores what it does not report, unlike on the server.
		$reporting = error_reporting(E_ALL);

		try {
			return $this->database()->request(fn(): Response => $this->app->core()->handle($request));
		} finally {
			error_reporting($reporting);
		}
	}

	/** @param array<string, string|list<string>> $headers */
	protected function get(string $uri, array $headers = []): Response
	{
		return $this->request($this->serverRequest('GET', $uri, $headers));
	}

	/**
	 * Posts the form as a browser does. With files, by field name, it is a
	 * multipart upload: each is the path of a local file or an uploaded
	 * file built with the app's factory.
	 *
	 * @param array<array-key, mixed> $form
	 * @param array<string, string|list<string>> $headers
	 * @param array<string, string|UploadedFileInterface> $files
	 */
	protected function post(string $uri, array $form = [], array $headers = [], array $files = []): Response
	{
		$factory = $this->app->factory();

		if ($files === []) {
			$request = $this
				->serverRequest('POST', $uri, ['Content-Type' => 'application/x-www-form-urlencoded', ...$headers])
				->withParsedBody($form)
				->withBody($factory->streamFactory()->createStream(http_build_query($form)));

			return $this->request($request);
		}

		$uploads = [];

		foreach ($files as $name => $file) {
			$uploads[$name] = $file instanceof UploadedFileInterface ? $file : $this->upload($file);
		}

		$request = $this
			->serverRequest('POST', $uri, ['Content-Type' => 'multipart/form-data; boundary=cosray', ...$headers])
			->withParsedBody($form)
			->withUploadedFiles($uploads);

		return $this->request($request);
	}

	/**
	 * Signs the user in for the following requests, with a session as the
	 * panel's sign-in starts it.
	 */
	protected function actingAs(User $user): static
	{
		$session = $this->session();
		$session->start();
		$session->regenerate();
		$session->setUser($user);
		$session->close();
		$this->signedIn = true;

		return $this;
	}

	/**
	 * Creates an active user of the site for the test, with a random
	 * password.
	 *
	 * @param list<string> $roles
	 */
	protected function user(array $roles = [], string $type = 'user', ?string $email = null): User
	{
		$email ??= bin2hex(random_bytes(6)) . '@example.com';

		return $this->service(Users::class)->create(
			$type,
			[
				'username' => null,
				'email' => $email,
				'name' => null,
				'roles' => $roles,
				'active' => true,
				'panelLocale' => null,
				'panelRegion' => null,
				'panelTimezone' => null,
				'content' => [],
			],
			bin2hex(random_bytes(16)),
			new Actor(1),
		);
	}

	/** The CSRF token of the session the next request continues. */
	protected function csrfToken(): string
	{
		$session = $this->session();
		$session->start();

		try {
			return $session->csrf->token();
		} finally {
			$session->close();
		}
	}

	private function session(): Session
	{
		$config = $this->app->config();

		return new Session($config->session->options, $config->app->name, $config->session->handler);
	}

	private function upload(string $path): UploadedFileInterface
	{
		$size = filesize($path);

		if (!is_file($path) || $size === false) {
			throw new RuntimeException("No file to upload at {$path}");
		}

		$type = mime_content_type($path);

		return $this->app
			->factory()
			->uploadedFileFactory()
			->createUploadedFile(
				$this->app->factory()->streamFactory()->createStreamFromFile($path),
				$size,
				UPLOAD_ERR_OK,
				basename($path),
				$type === false ? null : $type,
			);
	}

	/**
	 * A request from 127.0.0.1, to `http://localhost` unless the URI names a host.
	 *
	 * @param array<string, string|list<string>> $headers
	 */
	protected function serverRequest(string $method, string $uri, array $headers = []): Request
	{
		if (!str_contains($uri, '://')) {
			$uri = 'http://localhost/' . ltrim($uri, '/');
		}

		$request = $this->app->factory()->serverRequestFactory()->createServerRequest($method, $uri, [
			'REMOTE_ADDR' => '127.0.0.1',
		]);
		parse_str($request->getUri()->getQuery(), $query);
		$request = $request->withQueryParams($query);

		foreach ($headers as $name => $value) {
			$request = $request->withHeader($name, $value);
		}

		return $request;
	}

	/**
	 * A service of the app as a job or a console command gets it: without a
	 * request, in the default locale.
	 *
	 * @template T of object
	 *
	 * @param class-string<T> $class
	 * @return T
	 */
	protected function service(string $class): object
	{
		if ($this->runtime === null) {
			$this->runtime = new Runtime($this->app->container());
			Verba::activate($this->runtime->translator());
		}

		$service = $this->runtime->get($class);

		if (!$service instanceof $class) {
			throw new RuntimeException("Resolving {$class} returned " . get_debug_type($service) . '.');
		}

		return $service;
	}

	private function database(): Database
	{
		return $this->db ?? throw new RuntimeException('The test case has not set up the app.');
	}

	/**
	 * A request closes its session, and the CLI keeps the id for the next
	 * session_start(); reopen it so it can be destroyed.
	 */
	private static function endSession(): void
	{
		if (session_status() === PHP_SESSION_NONE && session_id() !== '') {
			session_start();
		}

		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION = [];
			session_unset();
			session_destroy();
		}
	}
}

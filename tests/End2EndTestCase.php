<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests;

use Celemas\Cms\Boiler\Error\Handler;
use Celemas\Cms\Cms;
use Celemas\Cms\Config;
use Celemas\Cms\Locale;
use Celemas\Cms\Locales;
use Celemas\Cms\Node\Node;
use Celemas\Cms\Plugin;
use Celemas\Core\App;
use Celemas\Core\Factory\Laminas;
use Celemas\Core\Plugin as CorePlugin;
use Celemas\Core\Request;
use Celemas\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;

/**
 * Base class for end-to-end tests that test the full HTTP request/response cycle.
 *
 * This class extends IntegrationTestCase and adds application setup with routing,
 * middleware, and CMS integration. Tests run through the full stack using in-process
 * HTTP requests (no external server needed).
 *
 * @internal
 *
 * @coversNothing
 */
class End2EndTestCase extends IntegrationTestCase
{
	protected App $app;
	protected ?\Celemas\Error\Handler $errorHandler = null;

	// Disable transactions because the CMS creates its own database connection
	// which cannot see uncommitted transaction data from the test connection.
	protected bool $useTransactions = false;

	// Track created test data for cleanup
	protected array $createdNodeIds = [];
	protected array $createdTypeHandles = [];
	protected array $createdUserIds = [];
	protected array $createdAuthTokens = [];
	protected array $createdOneTimeTokens = [];
	protected ?string $defaultAuthToken = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->app = $this->createApp();
	}

	protected function tearDown(): void
	{
		// Clean up test data created during the test
		$this->cleanupTestData();

		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION = [];
			session_unset();
			session_destroy();
		}

		// Restore error handlers to prevent PHPUnit warnings
		if ($this->errorHandler) {
			$this->errorHandler->restoreHandlers();
		}

		parent::tearDown();
	}

	/**
	 * Clean up dynamically created test data.
	 */
	protected function cleanupTestData(): void
	{
		$db = $this->db();

		// Delete created one-time tokens
		foreach ($this->createdOneTimeTokens as $tokenHash) {
			$db->execute('DELETE FROM cms.onetimetokens WHERE token = :token', [
				'token' => $tokenHash,
			])->run();
		}

		// Delete created auth tokens
		foreach ($this->createdAuthTokens as $tokenHash) {
			$db->execute('DELETE FROM cms.authtokens WHERE token = :token', ['token' => $tokenHash])->run();
		}

		// Delete created users
		foreach ($this->createdUserIds as $userId) {
			$db->execute('DELETE FROM cms.users WHERE usr = :usr', ['usr' => $userId])->run();
		}

		// Delete created paths and nodes in reverse order (children before parents)
		// Also delete related records that reference the nodes via FKs
		foreach (array_reverse($this->createdNodeIds) as $nodeId) {
			$db->execute('DELETE FROM cms.urlpaths WHERE node = :node', ['node' => $nodeId])->run();
			$db->execute('DELETE FROM cms.fulltext WHERE node = :node', ['node' => $nodeId])->run();
			$db->execute('DELETE FROM cms.nodetags WHERE node = :node', ['node' => $nodeId])->run();
			$db->execute('DELETE FROM cms.drafts WHERE node = :node', ['node' => $nodeId])->run();
			$db->execute('DELETE FROM audit.nodes WHERE node = :node', ['node' => $nodeId])->run();
			$db->execute('DELETE FROM cms.nodes WHERE node = :node', ['node' => $nodeId])->run();
		}

		// Delete created types
		foreach ($this->createdTypeHandles as $handle) {
			$db->execute('DELETE FROM cms.types WHERE handle = :handle', ['handle' => $handle])->run();
		}

		$this->createdNodeIds = [];
		$this->createdTypeHandles = [];
		$this->createdUserIds = [];
		$this->createdAuthTokens = [];
		$this->createdOneTimeTokens = [];
		$this->defaultAuthToken = null;
	}

	/**
	 * Track a node created via HTTP API by uid.
	 */
	protected function trackNodeByUid(string $uid): int
	{
		$node = $this->db()->execute(
			'SELECT node FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one();
		$this->assertNotEmpty($node);
		$nodeId = (int) $node['node'];

		if (!in_array($nodeId, $this->createdNodeIds, true)) {
			$this->createdNodeIds[] = $nodeId;
		}

		return $nodeId;
	}

	/**
	 * @override Track created types for cleanup
	 */
	protected function createTestType(string $handle): int
	{
		$typeId = parent::createTestType($handle);
		$this->createdTypeHandles[] = $handle;

		return $typeId;
	}

	/**
	 * @override Track created nodes for cleanup
	 */
	protected function createTestNode(array $data): int
	{
		$nodeId = parent::createTestNode($data);
		$this->createdNodeIds[] = $nodeId;

		return $nodeId;
	}

	/**
	 * Create an authenticated test user with an auth token.
	 *
	 * @param string $role The user role ('superuser', 'admin', 'editor')
	 * @return string The auth token to use in requests
	 */
	protected function createAuthenticatedUser(string $role = 'editor'): string
	{
		$db = $this->db();
		$uid = 'test-auth-' . uniqid();
		$token = bin2hex(random_bytes(32));
		$tokenHash = hash('sha256', $token);

		// Create user with correct schema (userrole instead of role)
		$sql = "INSERT INTO cms.users (uid, email, pwhash, userrole, active, data, creator, editor)
				VALUES (:uid, :email, :pwhash, :userrole, true, '{}'::jsonb, :creator, :editor)
				RETURNING usr";

		$systemUser = $db->execute(
			"SELECT usr FROM cms.users WHERE userrole = 'system' LIMIT 1",
		)->one();
		$this->assertNotEmpty($systemUser);
		$systemUserId = (int) $systemUser['usr'];

		$userId = $db->execute($sql, [
			'uid' => $uid,
			'email' => $uid . '@example.com',
			'pwhash' => password_hash('password', PASSWORD_ARGON2ID),
			'userrole' => $role,
			'creator' => $systemUserId,
			'editor' => $systemUserId,
		])->one()['usr'];

		$this->createdUserIds[] = $userId;

		// Create auth token
		$sql = 'INSERT INTO cms.authtokens (token, usr, creator, editor)
				VALUES (:token, :usr, 1, 1)';

		$db->execute($sql, [
			'token' => $tokenHash,
			'usr' => $userId,
		])->run();

		$this->createdAuthTokens[] = $tokenHash;

		return $token;
	}

	/**
	 * Set the default auth token for all subsequent requests.
	 */
	protected function authenticateAs(string $role = 'editor'): void
	{
		$this->defaultAuthToken = $this->createAuthenticatedUser($role);
	}

	protected function createApp(): App
	{
		$factory = new Laminas();
		$router = new Router();
		$container = $this->container();
		$config = $this->config([
			'db.dsn' => 'pgsql:host=localhost;dbname=celemascms;user=celemascms;password=celemascms',
			'path.root' => self::root(),
			'path.public' => self::root() . '/public',
			'path.views' => '/tests/Fixtures/templates',
			'path.uploads' => self::root() . '/public/uploads',
			'path.api' => '/api',
			'path.panel' => '/panel',
			'upload.maxSize' => 10 * 1024 * 1024, // 10MB
			'upload.allowedExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf'],
		]);

		$app = new App($factory, $router, $container);

		// Configure error handler middleware
		$this->errorHandler = $this->createErrorHandler($config, $factory);
		$app->middleware($this->errorHandler);

		// Load locales
		$app->load($this->createLocales());

		// Load CMS
		$plugin = $this->createPlugin($config);
		$app->load($plugin);
		$app->addRoute($plugin->catchallRoute());

		return $app;
	}

	protected function createLocales(): CorePlugin
	{
		$locales = new Locales();
		$locales->add('en', title: 'English', pgDict: 'english');
		$locales->add('de', title: 'Deutsch', fallback: 'en', pgDict: 'german');

		return $locales;
	}

	protected function createPlugin(Config $config): Plugin
	{
		$plugin = new Plugin($config);

		$plugin->node(\Celemas\Cms\Tests\Fixtures\Node\TestPage::class);
		$plugin->node(\Celemas\Cms\Tests\Fixtures\Node\TestArticle::class);
		$plugin->node(\Celemas\Cms\Tests\Fixtures\Node\TestHome::class);
		$plugin->node(\Celemas\Cms\Tests\Fixtures\Node\TestBlock::class);
		$plugin->node(\Celemas\Cms\Tests\Fixtures\Node\TestWidget::class);
		$plugin->node(\Celemas\Cms\Tests\Fixtures\Node\TestDocument::class);
		$plugin->node(\Celemas\Cms\Tests\Fixtures\Node\TestMediaDocument::class);

		return $plugin;
	}

	protected function createErrorHandler(Config $config, Laminas $factory): \Celemas\Error\Handler
	{
		$logger = new NullLogger();
		$handler = new Handler($config, $factory, $logger);
		$handler->views('tests/Fixtures/templates');

		$handler->trusted([
			Node::class,
			Cms::class,
			Locales::class,
			Locale::class,
			Config::class,
			Request::class,
		]);

		return $handler->create();
	}

	/**
	 * Make an HTTP request through the application.
	 *
	 * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
	 * @param string $uri Request URI
	 * @param array $options Request options:
	 *   - 'headers' => array - HTTP headers
	 *   - 'body' => string|array - Request body (array will be JSON encoded)
	 *   - 'query' => array - Query parameters
	 *   - 'cookies' => array - Cookie values
	 *   - 'authToken' => string - Auth token for authentication
	 * @return ResponseInterface PSR-7 response
	 */
	protected function makeRequest(string $method, string $uri, array $options = []): ResponseInterface
	{
		$psrRequest = $this->factory()->serverRequestFactory()->createServerRequest($method, $uri);

		// Add auth token (from options or default)
		$authToken = $options['authToken'] ?? $this->defaultAuthToken;

		if ($authToken) {
			$psrRequest = $psrRequest->withHeader('Authentication', 'Bearer ' . $authToken);
		}

		// Add query parameters
		if (isset($options['query'])) {
			$queryString = http_build_query($options['query']);
			$uriObj = $psrRequest->getUri()->withQuery($queryString);
			$psrRequest = $psrRequest
				->withUri($uriObj)
				->withQueryParams($options['query']);
		}

		// Add headers
		if (isset($options['headers'])) {
			foreach ($options['headers'] as $name => $value) {
				$psrRequest = $psrRequest->withHeader($name, $value);
			}
		}

		// Add cookies
		if (isset($options['cookies'])) {
			$cookieHeader = [];

			foreach ($options['cookies'] as $name => $value) {
				$cookieHeader[] = "{$name}={$value}";
			}
			$psrRequest = $psrRequest->withHeader('Cookie', implode('; ', $cookieHeader));
		}

		// Add body
		if (isset($options['body'])) {
			$body = $options['body'];

			if (is_array($body)) {
				$body = json_encode($body);
				$psrRequest = $psrRequest->withHeader('Content-Type', 'application/json');
			}

			$stream = $this->factory()->streamFactory()->createStream($body);
			$psrRequest = $psrRequest->withBody($stream);
		}

		// Capture output and return response without emitting
		ob_start();

		try {
			$response = $this->app->run($psrRequest);
		} finally {
			ob_end_clean();
		}

		return $response;
	}

	/**
	 * Assert that the response has the expected status code.
	 */
	protected function assertResponseStatus(
		int $expected,
		ResponseInterface $response,
		string $message = '',
	): void {
		$this->assertEquals(
			$expected,
			$response->getStatusCode(),
			$message ?: "Expected status code {$expected}, got {$response->getStatusCode()}",
		);
	}

	/**
	 * Assert that the response has a successful status code (2xx).
	 */
	protected function assertResponseOk(ResponseInterface $response): void
	{
		$statusCode = $response->getStatusCode();
		$this->assertGreaterThanOrEqual(200, $statusCode, 'Expected successful response');
		$this->assertLessThan(300, $statusCode, 'Expected successful response');
	}

	/**
	 * Get the response body as a decoded JSON array.
	 *
	 * @return array
	 */
	protected function getJsonResponse(ResponseInterface $response): array
	{
		$body = (string) $response->getBody();
		$decoded = json_decode($body, true);

		$this->assertIsArray($decoded, 'Response body is not valid JSON');

		return $decoded;
	}

	/**
	 * Assert JSON response and return decoded payload.
	 */
	protected function assertJsonResponse(ResponseInterface $response, ?int $status = null): array
	{
		if ($status !== null) {
			$this->assertResponseStatus($status, $response);
		}

		return $this->getJsonResponse($response);
	}

	/**
	 * Get the response body as HTML string.
	 */
	protected function getHtmlResponse(ResponseInterface $response): string
	{
		return (string) $response->getBody();
	}

	/**
	 * Assert that response contains specific header.
	 */
	protected function assertResponseHasHeader(string $header, ResponseInterface $response): void
	{
		$this->assertTrue(
			$response->hasHeader($header),
			"Response does not have header: {$header}",
		);
	}

	/**
	 * Assert that response header has expected value.
	 */
	protected function assertResponseHeaderEquals(
		string $header,
		string $expected,
		ResponseInterface $response,
	): void {
		$this->assertResponseHasHeader($header, $response);
		$actual = $response->getHeaderLine($header);
		$this->assertEquals($expected, $actual, "Header {$header} has unexpected value");
	}
}

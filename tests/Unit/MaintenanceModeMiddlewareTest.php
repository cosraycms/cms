<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\UnderMaintenance;
use Cosray\Maintenance;
use Cosray\Middleware\MaintenanceMode;
use Cosray\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class MaintenanceModeMiddlewareTest extends TestCase
{
	private string $file;

	protected function setUp(): void
	{
		parent::setUp();

		$this->file = sys_get_temp_dir() . '/cosray-maintenance-' . bin2hex(random_bytes(6)) . '.json';
	}

	protected function tearDown(): void
	{
		if (is_file($this->file)) {
			unlink($this->file);
		}

		parent::tearDown();
	}

	public function testEverythingPassesWhileTheModeIsOff(): void
	{
		$this->assertSame(200, $this->process('POST', '/contact')->getStatusCode());
	}

	public function testWritesAreRefusedAndReadsServed(): void
	{
		$this->maintenance()->on(retry: 90, message: 'Moving servers');

		$this->assertSame(200, $this->process('GET', '/')->getStatusCode());
		$this->assertSame(200, $this->process('HEAD', '/')->getStatusCode());

		foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
			$refused = $this->refusal($method, '/contact');
			$this->assertSame(503, $refused->statusCode());
			$this->assertSame(90, $refused->retry());
			$this->assertSame(['message' => 'Moving servers', 'retry' => 90], $refused->payload());
		}
	}

	public function testTheFullModeRefusesReadsToo(): void
	{
		$this->maintenance()->on(full: true);

		$this->assertSame(60, $this->refusal('GET', '/')->retry());
	}

	public function testTheApiAnswersWithProblemDetails(): void
	{
		$this->maintenance()->on(retry: 45, message: 'Back soon');

		$response = $this->process('POST', '/api/nodes', ['api.path' => '/api']);

		$this->assertSame(503, $response->getStatusCode());
		$this->assertSame('45', $response->getHeaderLine('Retry-After'));
		$this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
		$this->assertSame(
			['type' => 'about:blank', 'title' => 'Service Unavailable', 'status' => 503, 'detail' => 'Back soon'],
			json_decode((string) $response->getBody(), true),
		);
		$this->assertSame(200, $this->process('GET', '/api/nodes', ['api.path' => '/api'])->getStatusCode());
	}

	public function testTheBypassLinkSetsTheCookieThatLetsABrowserPass(): void
	{
		$secret = $this->maintenance()->on(full: true);

		$response = $this->process('GET', '/panel/maintenance?bypass=' . $secret);

		$this->assertSame(303, $response->getStatusCode());
		$this->assertSame('/panel', $response->getHeaderLine('Location'));
		$this->assertStringStartsWith(
			MaintenanceMode::COOKIE . '=' . $secret . ';',
			$response->getHeaderLine('Set-Cookie'),
		);
		$this->assertStringContainsString('HttpOnly', $response->getHeaderLine('Set-Cookie'));

		$passed = $this->process('POST', '/panel/node/abc', cookies: [MaintenanceMode::COOKIE => $secret]);
		$this->assertSame(200, $passed->getStatusCode());
	}

	public function testAWrongSecretDoesNotPass(): void
	{
		$this->maintenance()->on(full: true);

		$this->assertSame(503, $this->refusal('GET', '/panel/maintenance?bypass=wrong')->statusCode());
		$this->assertSame(503, $this->refusal('GET', '/', cookies: [MaintenanceMode::COOKIE => 'wrong'])->statusCode());
	}

	public function testAllowedPathsAreServedInFullMode(): void
	{
		$this->maintenance()->on(full: true);
		$settings = ['maintenance.allow' => ['/health']];

		$this->assertSame(200, $this->process('GET', '/health', $settings)->getStatusCode());
		$this->assertSame(200, $this->process('POST', '/health/ping', $settings)->getStatusCode());
		$this->assertSame(503, $this->refusal('GET', '/healthy', $settings)->statusCode());
	}

	public function testPathsAreMatchedBelowTheUrlPrefix(): void
	{
		$secret = $this->maintenance()->on();
		$settings = ['app.url_prefix' => '/cms', 'api.path' => '/api'];

		$this->assertSame(503, $this->process('POST', '/cms/api/nodes', $settings)->getStatusCode());

		$bypass = $this->process('GET', '/cms/panel/maintenance?bypass=' . $secret, $settings);
		$this->assertSame(303, $bypass->getStatusCode());
		$this->assertSame('/cms/panel', $bypass->getHeaderLine('Location'));
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, string> $cookies
	 */
	private function process(
		string $method,
		string $uri,
		array $settings = [],
		array $cookies = [],
	): ResponseInterface {
		$config = $this->config(['maintenance.file' => $this->file, ...$settings]);
		$request = $this
			->factory()
			->serverRequestFactory()
			->createServerRequest($method, $uri)
			->withCookieParams($cookies);
		parse_str($request->getUri()->getQuery(), $query);
		$middleware = new MaintenanceMode($config, Maintenance::of($config), $this->factory());

		return $middleware->process($request->withQueryParams($query), $this->handler());
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, string> $cookies
	 */
	private function refusal(
		string $method,
		string $uri,
		array $settings = [],
		array $cookies = [],
	): UnderMaintenance {
		try {
			$this->process($method, $uri, $settings, $cookies);
		} catch (UnderMaintenance $e) {
			return $e;
		}

		$this->fail("{$method} {$uri} was not refused.");
	}

	private function maintenance(): Maintenance
	{
		return new Maintenance($this->file);
	}

	private function handler(): RequestHandlerInterface
	{
		$factory = $this->factory();

		return new class($factory) implements RequestHandlerInterface {
			public function __construct(
				private readonly \Celema\Core\Factory\Factory $factory,
			) {}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return $this->factory->response(200);
			}
		};
	}
}

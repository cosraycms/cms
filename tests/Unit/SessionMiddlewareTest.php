<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Factory\Factory;
use Cosray\Middleware\Session as SessionMiddleware;
use Cosray\Session;
use Cosray\Tests\TestCase;
use Cosray\Users;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class SessionMiddlewareTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// A session the middleware closed keeps its id in the CLI; drop it.
		if (session_status() === PHP_SESSION_NONE && session_id() !== '') {
			session_id('');
		}
	}

	protected function tearDown(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION = [];
			session_unset();
			session_destroy();
		}

		parent::tearDown();
	}

	public function testExpiredSessionClearsUserId(): void
	{
		$config = $this->config([
			'session.options' => [
				'cookie_httponly' => true,
				'cookie_lifetime' => 0,
				'gc_maxlifetime' => 1,
				'use_cookies' => 0,
			],
		]);

		$session = new Session($config->session->options, $config->app->name);
		$session->start();
		$_SESSION['user_id'] = 42;
		$_SESSION['last_activity'] = time() - 10;

		$request = $this->factory()->serverRequestFactory()->createServerRequest('GET', '/');
		$middleware = new SessionMiddleware($config, new Users($this->db()));
		$handler = $this->recordingHandler();

		$before = time();
		$response = $middleware->process($request, $handler);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertNotNull($handler->request);

		$this->assertInstanceOf(Session::class, $handler->request->getAttribute('session'));
		$this->assertNull($handler->userId);
		$this->assertNull($handler->request->getAttribute('user'));
		$this->assertIsInt($handler->lastActivity);
		$this->assertGreaterThanOrEqual($before, $handler->lastActivity);
	}

	public function testSessionIsClosedWhenTheRequestEnds(): void
	{
		$config = $this->config(['session.options' => ['use_cookies' => 0]]);
		$middleware = new SessionMiddleware($config, new Users($this->db()));
		$request = $this->factory()->serverRequestFactory()->createServerRequest('GET', '/');

		$middleware->process($request, $this->recordingHandler());

		$this->assertSame(PHP_SESSION_NONE, session_status());
	}

	public function testSessionIsClosedWhenTheHandlerFails(): void
	{
		$config = $this->config(['session.options' => ['use_cookies' => 0]]);
		$middleware = new SessionMiddleware($config, new Users($this->db()));
		$request = $this->factory()->serverRequestFactory()->createServerRequest('GET', '/');
		$handler = new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				throw new RuntimeException('handler failed');
			}
		};

		try {
			$middleware->process($request, $handler);
			$this->fail('Expected the handler exception');
		} catch (RuntimeException $e) {
			$this->assertSame('handler failed', $e->getMessage());
		}

		$this->assertSame(PHP_SESSION_NONE, session_status());
	}

	/** Records what the request carried while the session was open. */
	private function recordingHandler(): RequestHandlerInterface
	{
		return new class($this->factory()) implements RequestHandlerInterface {
			public ?ServerRequestInterface $request = null;
			public ?int $userId = null;
			public ?int $lastActivity = null;

			public function __construct(
				private Factory $factory,
			) {}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				$this->request = $request;
				$session = $request->getAttribute('session');
				assert($session instanceof Session, 'The middleware adds the session');
				$this->userId = $session->authenticatedUserId();
				$this->lastActivity = $session->lastActivity();

				return $this->factory->responseFactory()->createResponse();
			}
		};
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Factory\Factory;
use Cosray\Middleware\Session as SessionMiddleware;
use Cosray\Session;
use Cosray\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class SessionMiddlewareTest extends TestCase
{
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
		$middleware = new SessionMiddleware($config, $this->db());
		$handler = new class($this->factory()) implements RequestHandlerInterface {
			public ?ServerRequestInterface $request = null;

			public function __construct(
				private Factory $factory,
			) {}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				$this->request = $request;

				return $this->factory->responseFactory()->createResponse();
			}
		};

		$before = time();
		$response = $middleware->process($request, $handler);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertNotNull($handler->request);

		$handledRequest = $handler->request;
		$sessionFromRequest = $handledRequest->getAttribute('session');
		$this->assertInstanceOf(Session::class, $sessionFromRequest);
		$this->assertNull($sessionFromRequest->authenticatedUserId());
		$this->assertNull($handledRequest->getAttribute('user'));
		$this->assertIsInt($sessionFromRequest->lastActivity());
		$this->assertGreaterThanOrEqual($before, $sessionFromRequest->lastActivity());
	}
}

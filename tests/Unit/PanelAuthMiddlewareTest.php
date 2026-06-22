<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\Factory\Factory;
use Celemas\Quma\Database;
use Cosray\Middleware\PanelAuth;
use Cosray\Session;
use Cosray\Tests\TestCase;
use Cosray\User;
use Cosray\Users;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelAuthMiddlewareTest extends TestCase
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

	public function testGuestRequestRedirectsToPanelLogin(): void
	{
		$middleware = new PanelAuth(
			$this->config(['path.panel' => '/admin']),
			$this->users(),
			$this->factory(),
		);
		$request = $this
			->factory()
			->serverRequestFactory()
			->createServerRequest(
				'GET',
				'/admin/collection/articles?parent=root',
			);

		$response = $middleware->process($request, $this->handler());

		$this->assertSame(303, $response->getStatusCode());
		$this->assertSame(
			'/admin/login?next=%2Fadmin%2Fcollection%2Farticles%3Fparent%3Droot',
			$response->getHeaderLine('Location'),
		);
	}

	public function testHtmxGuestRequestReturnsHxRedirectHeader(): void
	{
		$middleware = new PanelAuth(
			$this->config(['path.panel' => '/admin']),
			$this->users(),
			$this->factory(),
		);
		$request = $this->factory()->serverRequestFactory()->createServerRequest('GET', '/admin');
		$request = $request->withHeader('HX-Request', 'true');

		$response = $middleware->process($request, $this->handler());

		$this->assertSame(401, $response->getStatusCode());
		$this->assertSame('/admin/login?next=%2Fadmin', $response->getHeaderLine('HX-Redirect'));
	}

	public function testHtmxRequestWithoutPanelPermissionReturnsForbidden(): void
	{
		$session = new Session(['use_cookies' => 0], 'panel-auth');
		$session->start();
		$session->setUser(42);

		$user = new User([
			'usr' => 42,
			'uid' => 'guest',
			'username' => 'guest',
			'email' => 'guest@example.com',
			'password' => 'hash',
			'role' => 'guest',
			'active' => true,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
			'expires' => null,
		]);

		$middleware = new PanelAuth(
			$this->config(['path.panel' => '/admin']),
			$this->users([$user]),
			$this->factory(),
		);
		$request = $this->factory()->serverRequestFactory()->createServerRequest('GET', '/admin');
		$request = $request
			->withHeader('HX-Request', 'true')
			->withAttribute('session', $session);

		$response = $middleware->process($request, $this->handler());

		$this->assertSame(403, $response->getStatusCode());
		$this->assertSame('/admin/login?next=%2Fadmin', $response->getHeaderLine('HX-Redirect'));
	}

	public function testPanelUserCanAccessProtectedRoute(): void
	{
		$session = new Session(['use_cookies' => 0], 'panel-auth');
		$session->start();
		$session->setUser(7);

		$admin = new User([
			'usr' => 7,
			'uid' => 'admin',
			'username' => 'admin',
			'email' => 'admin@example.com',
			'password' => 'hash',
			'role' => 'admin',
			'active' => true,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
			'expires' => null,
		]);

		$middleware = new PanelAuth(
			$this->config(['path.panel' => '/admin']),
			$this->users([$admin]),
			$this->factory(),
		);
		$request = $this->factory()->serverRequestFactory()->createServerRequest('GET', '/admin');
		$request = $request->withAttribute('session', $session);

		$response = $middleware->process($request, $this->handler(204));

		$this->assertSame(204, $response->getStatusCode());
	}

	/**
	 * @param list<User> $items
	 */
	private function users(array $items = []): Users
	{
		return new class($this->db(), $items) extends Users {
			/** @param list<User> $items */
			public function __construct(
				Database $db,
				private array $items,
			) {
				parent::__construct($db);
			}

			public function byId(int $id): ?User
			{
				foreach ($this->items as $user) {
					if ($user->id === $id) {
						return $user;
					}
				}

				return null;
			}

			public function byAuthToken(#[\SensitiveParameter] string $token): ?User
			{
				return null;
			}
		};
	}

	private function handler(int $status = 200): RequestHandlerInterface
	{
		return new class($this->factory(), $status) implements RequestHandlerInterface {
			public function __construct(
				private Factory $factory,
				private int $status,
			) {}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return $this->factory->responseFactory()->createResponse($this->status);
			}
		};
	}
}

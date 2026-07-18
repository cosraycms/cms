<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpUnauthorized;
use Celema\Core\Factory\Factory;
use Celema\Quma\Database;
use Cosray\Middleware\Permission;
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
final class PermissionMiddlewareTest extends TestCase
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

	public function testUnauthorizedWhenNoUser(): void
	{
		$middleware = new Permission('edit-users');
		$middleware->init(new Users($this->db()), $this->config());
		$request = $this->factory()->serverRequestFactory()->createServerRequest('GET', '/');

		$this->throws(HttpUnauthorized::class);
		$middleware->process($request, $this->handler());
	}

	public function testForbiddenWhenPermissionMissing(): void
	{
		$session = new Session(['use_cookies' => 0], 'permission-test');
		$session->start();
		$session->setUser(42);

		$user = new User([
			'usr' => 42,
			'uid' => 'test-editor',
			'username' => 'test-editor',
			'email' => 'editor@example.com',
			'password' => 'hash',
			'role' => 'editor',
			'active' => true,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
			'expires' => null,
		]);

		$users = new class($this->db(), $user) extends Users {
			public function __construct(
				Database $db,
				private User $user,
			) {
				parent::__construct($db);
			}

			public function byId(int $id): ?User
			{
				if ($id === $this->user->id) {
					return $this->user;
				}

				return null;
			}
		};

		$middleware = new Permission('edit-users');
		$middleware->init($users, $this->config());
		$request = $this->factory()->serverRequestFactory()->createServerRequest('GET', '/');
		$request = $request->withAttribute('session', $session);

		$this->throws(HttpForbidden::class);
		$middleware->process($request, $this->handler());
	}

	private function handler(): RequestHandlerInterface
	{
		return new class($this->factory()) implements RequestHandlerInterface {
			public function __construct(
				private Factory $factory,
			) {}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return $this->factory->responseFactory()->createResponse();
			}
		};
	}
}

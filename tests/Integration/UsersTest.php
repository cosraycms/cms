<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Actor;
use Cosray\Tests\Fixtures\User\Teacher;
use Cosray\Tests\IntegrationTestCase;
use Cosray\User;
use Cosray\User\Types;
use Cosray\Users;

final class UsersTest extends IntegrationTestCase
{
	private const array VALUES = [
		'username' => 'ada',
		'email' => 'ada@example.com',
		'name' => 'Ada Lovelace',
		'roles' => ['editor', 'admin'],
		'active' => true,
		'panelLocale' => null,
		'content' => [],
	];

	public function testACreatedUserCanSignInAndHoldsItsRoles(): void
	{
		$users = new Users($this->db());
		$user = $users->create('user', self::VALUES, 'correct horse battery', Actor::system());

		$this->assertSame(['editor', 'admin'], $user->roles);
		$this->assertSame('Ada Lovelace', $user->name);
		$this->assertTrue(password_verify('correct horse battery', $users->byLogin('ada')->password));
	}

	public function testUpdatingKeepsForeignDataAndDeactivationHidesTheUserFromLogin(): void
	{
		$users = new Users($this->db());
		$user = $users->create('user', self::VALUES, 'correct horse battery', Actor::system());
		$this->db()->execute(
			"UPDATE cms.users SET data = data || '{\"crm\": 7}'::jsonb WHERE usr = :usr",
			['usr' => $user->id],
		)->run();

		$users->update(
			$users->find($user->uid),
			['name' => 'Ada King', 'active' => false, 'roles' => []] + self::VALUES,
			Actor::system(),
		);
		$saved = $users->find($user->uid);

		$this->assertSame('Ada King', $saved->name);
		$this->assertSame(7, $saved->data()['crm']);
		$this->assertSame([], $saved->roles);
		$this->assertNull($users->byLogin('ada'));
	}

	public function testADeletedUserFreesItsLoginsAndDropsRememberedSessions(): void
	{
		$users = new Users($this->db());
		$user = $users->create('user', self::VALUES, 'correct horse battery', Actor::system());
		$users->remember('remembered-hash', $user->id, date(DATE_ATOM, time() + 3600));

		$this->assertSame(['email' => true, 'username' => true], $users->taken('ADA@example.com', 'Ada'));
		$this->assertSame(['email' => false, 'username' => false], $users->taken('ada@example.com', 'ada', $user));

		$users->delete($user, Actor::system());

		$this->assertNull($users->find($user->uid));
		$this->assertNull($users->bySession('remembered-hash'));
		$this->assertSame(['email' => false, 'username' => false], $users->taken('ada@example.com', 'ada'));
	}

	public function testAChangedPasswordDropsRememberedSessions(): void
	{
		$users = new Users($this->db());
		$user = $users->create('user', self::VALUES, 'correct horse battery', Actor::system());
		$users->remember('remembered-hash', $user->id, date(DATE_ATOM, time() + 3600));

		$users->setPassword($user, 'a different long password', Actor::system());

		$this->assertNull($users->bySession('remembered-hash'));
		$this->assertTrue(password_verify('a different long password', $users->find($user->uid)->password));
	}

	public function testTheListIsFilteredByTypeAndSearchAndLeavesOutTheSystemUser(): void
	{
		$users = new Users($this->db());
		$users->create('user', self::VALUES, 'correct horse battery', Actor::system());
		$users->create(
			'customer',
			['username' => null, 'email' => '100%@example.com', 'name' => null, 'roles' => []] + self::VALUES,
			'correct horse battery',
			Actor::system(),
		);

		$this->assertSame(['100%@example.com'], array_column($users->list('customer', '', 50, 0), 'email'));
		$this->assertSame(['ada@example.com'], array_column($users->list(null, 'lovelace', 50, 0), 'email'));
		$this->assertSame(['100%@example.com'], array_column($users->list(null, '0%@', 50, 0), 'email'));
		$this->assertSame(1, $users->count('customer', ''));
		$this->assertNotContains('system@cosray.dev', array_column($users->list(null, '', 500, 0), 'email'));
	}

	public function testTheLastActiveSuperuserIsRecognised(): void
	{
		$users = new Users($this->db());
		$this->db()->execute("UPDATE cms.users SET active = false WHERE 'superuser' = ANY(roles)")->run();
		$first = $users->create(
			'user',
			['roles' => ['superuser']] + self::VALUES,
			'correct horse battery',
			Actor::system(),
		);

		$this->assertTrue($users->isLastSuperuser($first));

		$users->create(
			'user',
			['roles' => ['superuser'], 'username' => 'grace', 'email' => 'grace@example.com'] + self::VALUES,
			'correct horse battery',
			Actor::system(),
		);

		$this->assertFalse($users->isLastSuperuser($first));
	}

	public function testAUserIsBuiltFromTheClassRegisteredForItsType(): void
	{
		$id = $this->createTestUser(['uid' => 'typed-teacher']);
		$this->db()->execute("UPDATE cms.users SET type = 'teacher' WHERE usr = :usr", ['usr' => $id])->run();
		$types = new Types();
		$types->add(Teacher::class);

		$this->assertInstanceOf(Teacher::class, new Users($this->db(), $types)->byId($id));
		$this->assertSame(User::class, new Users($this->db())->byId($id)::class);
		$this->assertSame('teacher', new Users($this->db())->byId($id)->type);
	}
}

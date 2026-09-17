<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Tests\Fixtures\User\Teacher;
use Cosray\Tests\IntegrationTestCase;
use Cosray\User;
use Cosray\User\Types;
use Cosray\Users;

final class UsersTest extends IntegrationTestCase
{
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

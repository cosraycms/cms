<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\User;

final class UserTest extends TestCase
{
	public function testNameComesFromStoredJson(): void
	{
		$this->assertSame('Max Keller', $this->user(['data' => '{"name": " Max Keller "}'])->name);
		$this->assertSame('Max Keller', $this->user(['data' => ['name' => 'Max Keller']])->name);
	}

	public function testMissingOrBlankNameIsNull(): void
	{
		$this->assertNull($this->user([])->name);
		$this->assertNull($this->user(['data' => '{}'])->name);
		$this->assertNull($this->user(['data' => '{"name": "  "}'])->name);
		$this->assertNull($this->user(['data' => '{"name": 42}'])->name);
	}

	public function testAvatarDescribesTheUser(): void
	{
		$avatar = $this->user(['uid' => 'u-1', 'data' => '{"name": "Max Keller"}'])->avatar();

		$this->assertSame('u-1', $avatar->uid);
		$this->assertSame('Max Keller', $avatar->label);
		$this->assertSame('MK', $avatar->initials);
	}

	public function testAvatarFallsBackToTheEmailsLocalPart(): void
	{
		$avatar = $this->user(['username' => null, 'email' => 'max_keller@example.com'])->avatar();

		$this->assertSame('max_keller@example.com', $avatar->label);
		$this->assertSame('MK', $avatar->initials);
	}

	/** @param array<string, mixed> $row */
	private function user(array $row): User
	{
		return new User(array_merge([
			'usr' => 42,
			'uid' => 'editor',
			'username' => 'editor',
			'email' => 'editor@example.com',
			'password' => 'hash',
			'roles' => ['editor'],
			'active' => true,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
		], $row));
	}
}

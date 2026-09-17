<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\User;
use PHPUnit\Framework\Attributes\DataProvider;

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

	/** @return array<string, array{array<string, mixed>, string}> */
	public static function initialsProvider(): array
	{
		return [
			'full name' => [['data' => '{"name": "Max Keller"}'], 'MK'],
			'first and last word' => [['data' => '{"name": "Anna-Lena van der Berg"}'], 'AB'],
			'single name' => [['data' => '{"name": "Max"}'], 'MA'],
			'multibyte' => [['data' => '{"name": "élise özdemir"}'], 'ÉÖ'],
			'username without name' => [['username' => 'm.keller'], 'MK'],
			'email local part' => [['username' => null, 'email' => 'max_keller@example.com'], 'MK'],
			'single-word email' => [['username' => null, 'email' => 'ernst@example.com'], 'ER'],
		];
	}

	/** @param array<string, mixed> $row */
	#[DataProvider('initialsProvider')]
	public function testInitials(array $row, string $expected): void
	{
		$this->assertSame($expected, $this->user($row)->initials());
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

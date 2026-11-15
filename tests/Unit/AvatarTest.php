<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\User\Avatar;
use PHPUnit\Framework\Attributes\DataProvider;

final class AvatarTest extends TestCase
{
	public function testHueDependsOnTheIdAlone(): void
	{
		$before = new Avatar(7, 'Max Keller', 'max', 'max@example.com');
		$after = new Avatar(7, 'Maximilian Keller-Brandt', 'mkb', 'mkb@example.org');

		$this->assertSame($before->hue, $after->hue);
	}

	public function testConsecutiveUsersGetDistinctHues(): void
	{
		$hues = array_map(static fn(int $id): int => new Avatar($id, null, 'user', '')->hue, range(1, 12));
		sort($hues);
		$gaps = array_map(
			static fn(int $index): int => ($hues[($index + 1) % 12] - $hues[$index] + 360) % 360,
			range(0, 11),
		);

		$this->assertGreaterThanOrEqual(0, $hues[0]);
		$this->assertLessThan(360, $hues[11]);
		$this->assertGreaterThanOrEqual(15, min($gaps));
	}

	public function testLabelPrefersNameThenUsernameThenEmail(): void
	{
		$this->assertSame('Max Keller', new Avatar(1, ' Max Keller ', 'max', 'max@example.com')->label);
		$this->assertSame('max', new Avatar(1, '  ', 'max', 'max@example.com')->label);
		$this->assertSame('max@example.com', new Avatar(1, null, '', 'max@example.com')->label);
		$this->assertSame('max', (string) new Avatar(1, null, 'max', 'max@example.com'));
	}

	/** @return array<string, array{?string, string, string, string}> */
	public static function initialsProvider(): array
	{
		return [
			'full name' => ['Max Keller', 'max', 'max@example.com', 'MK'],
			'first and last word' => ['Anna-Lena van der Berg', '', 'a@example.com', 'AB'],
			'single name' => ['Max', '', 'max@example.com', 'MA'],
			'multibyte' => ['élise özdemir', '', 'e@example.com', 'ÉÖ'],
			'username without name' => [null, 'm.keller', 'max@example.com', 'MK'],
			'email local part' => [null, '', 'max_keller@example.com', 'MK'],
			'single-word email' => [null, '', 'ernst@example.com', 'ER'],
			'nothing' => [null, '', '', ''],
		];
	}

	#[DataProvider('initialsProvider')]
	public function testInitials(?string $name, string $username, string $email, string $expected): void
	{
		$this->assertSame($expected, new Avatar(1, $name, $username, $email)->initials);
	}

	public function testArrayCarriesWhatTheViewNeeds(): void
	{
		$avatar = new Avatar(1, 'Max Keller', 'max', 'max@example.com');

		$this->assertSame(
			['label' => 'Max Keller', 'initials' => 'MK', 'hue' => 137],
			$avatar->array(),
		);
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\User\Avatar;
use PHPUnit\Framework\Attributes\DataProvider;

final class AvatarTest extends TestCase
{
	public function testHueDependsOnTheUidAlone(): void
	{
		$before = new Avatar('3f2a9c', 'Max Keller', 'max', 'max@example.com');
		$after = new Avatar('3f2a9c', 'Maximilian Keller-Brandt', 'mkb', 'mkb@example.org');

		$this->assertSame($before->hue, $after->hue);
		$this->assertNotSame($before->hue, new Avatar('8d01e4', 'Max Keller', 'max', 'max@example.com')->hue);
	}

	public function testHueIsAnAngle(): void
	{
		foreach (['', 'a', 'editor', '0193a7e2-6c1f-7b42-9a55-3d8e6f2c1b07'] as $uid) {
			$hue = new Avatar($uid, null, 'user', '')->hue;

			$this->assertGreaterThanOrEqual(0, $hue);
			$this->assertLessThan(360, $hue);
		}
	}

	public function testLabelPrefersNameThenUsernameThenEmail(): void
	{
		$this->assertSame('Max Keller', new Avatar('u', ' Max Keller ', 'max', 'max@example.com')->label);
		$this->assertSame('max', new Avatar('u', '  ', 'max', 'max@example.com')->label);
		$this->assertSame('max@example.com', new Avatar('u', null, '', 'max@example.com')->label);
		$this->assertSame('max', (string) new Avatar('u', null, 'max', 'max@example.com'));
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
		$this->assertSame($expected, new Avatar('u', $name, $username, $email)->initials);
	}

	public function testArrayCarriesWhatTheViewNeeds(): void
	{
		$avatar = new Avatar('u-1', 'Max Keller', 'max', 'max@example.com');

		$this->assertSame(
			['uid' => 'u-1', 'label' => 'Max Keller', 'initials' => 'MK', 'hue' => $avatar->hue],
			$avatar->array(),
		);
	}
}

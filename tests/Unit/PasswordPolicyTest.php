<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\User\PasswordPolicy;
use Cosray\User\PasswordProblem;

/**
 * @internal
 *
 * @coversNothing
 */
final class PasswordPolicyTest extends TestCase
{
	public function testALengthOfTwelveTo512Characters(): void
	{
		$policy = $this->policy();

		$this->assertSame(PasswordProblem::Short, $policy->check('xK4#mQ9!vL2'));
		$this->assertNull($policy->check('xK4#mQ9!vL2&'));
		$this->assertSame(PasswordProblem::Long, $policy->check(str_repeat('xK4#mQ9!vL2&', 43)));
		$this->assertSame(PasswordProblem::Short, $policy->check('Grüße-Köln'), 'counts characters, not bytes');
	}

	public function testRunsSequencesAndRepeatsCountAsOneCharacter(): void
	{
		$policy = $this->policy();
		$guessable = [
			'111111111111',
			'123456789012',
			'abcdefghijkl',
			'zyxwvutsrqpo',
			'abababababab',
			'passwordpassword',
			'hallohallo12',
			'Password1234',
			'iloveyou1234',
			'qwertzuiop12',
			'zxcvbnmasdfg',
			'1q2w3e4r5t6z',
			'1qaz2wsx3edc',
			'!@#$%^&*()ab',
		];

		foreach ($guessable as $password) {
			$this->assertSame(PasswordProblem::Guessable, $policy->check($password), $password);
		}

		foreach (['correct horse battery staple', 'Hund1234Katze', 'Tr0ub4dor&3x', 'Grüße-aus-Köln'] as $password) {
			$this->assertNull($policy->check($password), $password);
		}
	}

	public function testPartsOfTheAccountAndTheSiteCountAsOneCharacter(): void
	{
		$policy = $this->policy(['app.name' => 'Sample Site', 'app.url' => 'https://www.example.org']);
		$account = ['erika.mustermann@example.com', 'erika', 'Erika Mustermann'];

		foreach (['Mustermann2026!', 'Erika.Mustermann', 'SampleSite2026', 'www.example.org'] as $password) {
			$this->assertSame(PasswordProblem::Personal, $policy->check($password, ...$account), $password);
		}

		$this->assertNull($policy->check('Mustermann2026!', 'jane@example.com', null, 'Jane Roe'));
		$this->assertNull($policy->check('Erika-fährt-nach-Rom', ...$account), 'enough remains beside the name');
	}

	/** @param array<string, mixed> $settings */
	private function policy(array $settings = []): PasswordPolicy
	{
		return new PasswordPolicy($this->config($settings));
	}
}

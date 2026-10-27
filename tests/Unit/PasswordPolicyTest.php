<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\Fixtures\RecordingLogger;
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
	private const string HASH = 'ABF7AAD6438836DBE526AA231ABDE2D0EEF74D42';

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

	public function testTheBreachCheckIsOffByDefault(): void
	{
		$policy = new PasswordPolicy($this->config(), new RecordingLogger(), function (): never {
			$this->fail('The range API must not be asked');
		});

		$this->assertNull($policy->check('correct horse battery staple'));
	}

	public function testTheBreachCheckSendsOnlyTheStartOfTheHash(): void
	{
		$urls = [];
		$policy = $this->breachPolicy(function (string $url) use (&$urls): string {
			$urls[] = $url;

			return "0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n" . substr(self::HASH, 5) . ":42\r\n";
		});

		$this->assertSame(PasswordProblem::Breached, $policy->check('correct horse battery staple'));
		$this->assertSame(['https://api.pwnedpasswords.com/range/' . substr(self::HASH, 0, 5)], $urls);
	}

	public function testPaddingAndOtherSuffixesPass(): void
	{
		$padded = $this->breachPolicy(static fn(): string => substr(self::HASH, 5) . ":0\r\n");
		$other = $this->breachPolicy(static fn(): string => "0018A45C4D1DEF81644B54AB7F969B88D65:3\r\n");

		$this->assertNull($padded->check('correct horse battery staple'));
		$this->assertNull($other->check('correct horse battery staple'));
	}

	public function testWithoutAnAnswerThePasswordPassesAndTheMissedCheckIsLogged(): void
	{
		$logger = new RecordingLogger();
		$policy = $this->breachPolicy(static fn(): ?string => null, $logger);

		$this->assertNull($policy->check('correct horse battery staple'));
		$this->assertSame('warning', $logger->records[0][0] ?? null);
	}

	/** @param array<string, mixed> $settings */
	private function policy(array $settings = []): PasswordPolicy
	{
		return new PasswordPolicy($this->config($settings), new RecordingLogger());
	}

	private function breachPolicy(\Closure $fetch, ?RecordingLogger $logger = null): PasswordPolicy
	{
		return new PasswordPolicy(
			$this->config(['password.breach_check' => true]),
			$logger ?? new RecordingLogger(),
			$fetch,
		);
	}
}

<?php

declare(strict_types=1);

namespace Cosray\User;

use Closure;
use Cosray\Config;
use Psr\Log\LoggerInterface as Logger;

/**
 * Decides whether a password may be set. Runs of one character, sequences
 * along the alphabet, the digits or a keyboard row, either way, and parts
 * repeated from earlier in the password each count as one character, and
 * so do parts of the account's name, login or email address and of the
 * site's name and host; at least MIN_REMAINING characters must remain.
 *
 * With `password.breach_check`, Have I Been Pwned's range API tells whether
 * the password appeared in a breach. It only learns the first five
 * characters of the password's SHA-1; without an answer the password passes.
 */
final class PasswordPolicy
{
	public const int MIN_LENGTH = 12;
	public const int MAX_LENGTH = 512;

	/** Characters that must remain once each pattern counts as one. */
	public const int MIN_REMAINING = 10;

	private const int MIN_PATTERN = 3;
	private const int MIN_PERSONAL = 4;
	private const string RANGE_URL = 'https://api.pwnedpasswords.com/range/';

	/** Keyboard rows of the QWERTY, QWERTZ and AZERTY layouts, their columns and walks between two rows. */
	private const array SEQUENCES = [
		'abcdefghijklmnopqrstuvwxyz',
		'01234567890',
		'!@#$%^&*()',
		'!"§$%&/()=',
		'qwertyuiop',
		'qwertzuiopü',
		'azertyuiop',
		'asdfghjkl',
		'asdfghjklöä',
		'qsdfghjklm',
		'zxcvbnm',
		'yxcvbnm',
		'wxcvbn',
		'1qaz2wsx3edc4rfv5tgb6yhn7ujm8ik9ol0p',
		'1qay2wsx3edc4rfv5tgb6zhn7ujm8ik9ol0p',
		'1q2w3e4r5t6y7u8i9o0p',
		'1q2w3e4r5t6z7u8i9o0p',
		'q1w2e3r4t5y6u7i8o9p0',
		'q1w2e3r4t5z6u7i8o9p0',
	];

	/** @var ?list<string> */
	private static ?array $sequences = null;

	/** @var Closure(string): ?string */
	private readonly Closure $fetch;

	/** @param ?Closure(string): ?string $fetch answers a range request, or returns null */
	public function __construct(
		private readonly Config $config,
		private readonly Logger $logger,
		?Closure $fetch = null,
	) {
		$this->fetch = $fetch ?? $this->request(...);
	}

	/** Why the password may not be set, or null if it may; $personal are the account's name, login and email address. */
	public function check(#[\SensitiveParameter] string $password, ?string ...$personal): ?PasswordProblem
	{
		$length = mb_strlen($password);

		if ($length < self::MIN_LENGTH) {
			return PasswordProblem::Short;
		}

		if ($length > self::MAX_LENGTH) {
			return PasswordProblem::Long;
		}

		$chars = mb_str_split(mb_strtolower($password));

		if (self::pieces($chars) < self::MIN_REMAINING) {
			return PasswordProblem::Guessable;
		}

		if (self::pieces($chars, $this->personal($personal)) < self::MIN_REMAINING) {
			return PasswordProblem::Personal;
		}

		if ($this->config->password->breachCheck && $this->breached($password)) {
			return PasswordProblem::Breached;
		}

		return null;
	}

	/**
	 * How many pieces the password falls into when each pattern, and each
	 * part of a personal value, counts as one.
	 *
	 * @param list<string> $chars
	 * @param list<string> $personal
	 */
	private static function pieces(array $chars, array $personal = []): int
	{
		$pieces = 0;
		$at = 0;

		while ($at < count($chars)) {
			$at += max(
				1,
				self::run($chars, $at),
				self::repeat($chars, $at),
				self::within($chars, $at, self::sequences(), self::MIN_PATTERN),
				self::within($chars, $at, $personal, self::MIN_PERSONAL),
			);
			$pieces++;
		}

		return $pieces;
	}

	/** @param list<string> $chars */
	private static function run(array $chars, int $at): int
	{
		$end = $at + 1;

		while (($chars[$end] ?? null) === $chars[$at]) {
			$end++;
		}

		return ($end - $at) >= self::MIN_PATTERN ? $end - $at : 0;
	}

	/**
	 * The longest part at $at that also begins earlier; it may overlap
	 * itself, as in abababab.
	 *
	 * @param list<string> $chars
	 */
	private static function repeat(array $chars, int $at): int
	{
		$length = 0;

		for ($k = self::MIN_PATTERN; ($at + $k) <= count($chars); $k++) {
			$part = implode('', array_slice($chars, $at, $k));

			if (!str_contains(implode('', array_slice($chars, 0, $at + $k - 1)), $part)) {
				break;
			}

			$length = $k;
		}

		return $length;
	}

	/**
	 * The longest part at $at, of at least $min characters, found in one of
	 * the haystacks.
	 *
	 * @param list<string> $chars
	 * @param list<string> $haystacks
	 */
	private static function within(array $chars, int $at, array $haystacks, int $min): int
	{
		$length = 0;

		for ($k = $min; ($at + $k) <= count($chars); $k++) {
			$part = implode('', array_slice($chars, $at, $k));

			if (!array_any($haystacks, static fn(string $haystack): bool => str_contains($haystack, $part))) {
				break;
			}

			$length = $k;
		}

		return $length;
	}

	/** @return list<string> */
	private static function sequences(): array
	{
		return self::$sequences ??= [
			...self::SEQUENCES,
			...array_map(
				static fn(string $sequence): string => implode('', array_reverse(mb_str_split($sequence))),
				self::SEQUENCES,
			),
		];
	}

	/**
	 * @param array<array-key, ?string> $values
	 * @return list<string>
	 */
	private function personal(array $values): array
	{
		$values[] = $this->config->app->name;
		$values[] = $this->config->app->title;
		$values[] = parse_url((string) $this->config->app->url, PHP_URL_HOST);

		return array_values(array_filter(
			array_map(static fn(mixed $value): string => mb_strtolower(trim((string) $value)), $values),
			static fn(string $value): bool => mb_strlen($value) >= self::MIN_PERSONAL,
		));
	}

	private function breached(#[\SensitiveParameter] string $password): bool
	{
		$hash = strtoupper(sha1($password));
		$range = ($this->fetch)(self::RANGE_URL . substr($hash, 0, 5));

		if ($range === null) {
			$this->logger->warning('Password breach check skipped: {url} did not answer', ['url' => self::RANGE_URL]);

			return false;
		}

		$suffix = substr($hash, 5) . ':';

		foreach (explode("\n", $range) as $line) {
			if (str_starts_with($line, $suffix)) {
				// Padding lines have a count of 0.
				return (int) substr(trim($line), strlen($suffix)) > 0;
			}
		}

		return false;
	}

	private function request(string $url): ?string
	{
		$handle = curl_init($url);
		curl_setopt_array($handle, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_TIMEOUT => 5,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_HTTPHEADER => ['Add-Padding: true'],
			CURLOPT_USERAGENT => 'cosray/cms',
		]);
		$body = curl_exec($handle);
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

		return is_string($body) && $status === 200 ? $body : null;
	}
}

<?php

declare(strict_types=1);

namespace Cosray\User;

/**
 * Decides whether a password may be set. Runs of one character, sequences
 * along the alphabet, the digits or a keyboard row, either way, and parts
 * repeated from earlier in the password each count as one character; at
 * least MIN_REMAINING characters must remain.
 */
final class PasswordPolicy
{
	public const int MIN_LENGTH = 12;
	public const int MAX_LENGTH = 512;

	/** Characters that must remain once each pattern counts as one. */
	public const int MIN_REMAINING = 10;

	private const int MIN_PATTERN = 3;

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

	/** Why the password may not be set, or null if it may. */
	public function check(#[\SensitiveParameter] string $password): ?PasswordProblem
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

		return null;
	}

	/**
	 * How many pieces the password falls into when each pattern counts as one.
	 *
	 * @param list<string> $chars
	 */
	private static function pieces(array $chars): int
	{
		$pieces = 0;
		$at = 0;

		while ($at < count($chars)) {
			$at += max(
				1,
				self::run($chars, $at),
				self::repeat($chars, $at),
				self::within($chars, $at, self::sequences(), self::MIN_PATTERN),
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
}

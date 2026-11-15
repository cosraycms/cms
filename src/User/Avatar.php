<?php

declare(strict_types=1);

namespace Cosray\User;

use Override;
use Stringable;

/**
 * A user's initials badge. Its hue steps by the golden angle per user id, so
 * users created one after another sit far apart on the colour wheel and a
 * rename keeps the colour; the panel's stylesheet sets lightness and chroma
 * per theme.
 */
final readonly class Avatar implements Stringable
{
	/** The name shown for the user: the display name, else the username, else the email. */
	public string $label;
	public string $initials;
	public int $hue;

	private const float GOLDEN_ANGLE = 137.50776405003785;

	public function __construct(
		int $id,
		?string $name,
		string $username,
		string $email,
	) {
		$name = $name !== null && trim($name) !== '' ? trim($name) : null;
		$this->label = $name ?? ($username !== '' ? $username : $email);
		$this->initials = self::initialsOf(
			$name ?? ($username !== '' ? $username : (string) strstr($email, '@', true)),
		);
		$this->hue = (int) fmod($id * self::GOLDEN_ANGLE, 360);
	}

	/** @return array{label: string, initials: string, hue: int} */
	public function array(): array
	{
		return [
			'label' => $this->label,
			'initials' => $this->initials,
			'hue' => $this->hue,
		];
	}

	#[Override]
	public function __toString(): string
	{
		return $this->label;
	}

	/**
	 * First and last word ("m.keller" → "MK"); a single word gives its first
	 * two letters.
	 */
	private static function initialsOf(string $source): string
	{
		$words = preg_split('/[\s._-]+/u', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];

		$initials = match (count($words)) {
			0 => '',
			1 => mb_substr($words[0], 0, 2),
			default => mb_substr($words[0], 0, 1) . mb_substr($words[array_key_last($words)], 0, 1),
		};

		return mb_strtoupper($initials);
	}
}

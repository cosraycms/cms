<?php

declare(strict_types=1);

namespace Cosray;

use InvalidArgumentException;

final class Uid
{
	public const ALPHABET_ALPHANUMERIC = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	public const ALPHABET_LOWERCASE_WORD_SAFE = '123456789bcdfghklmnpqrstvwxyz';
	public const ALPHABET_CROCKFORD_BASE_32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
	public const ALPHABET_WORD_SAFE = 'FGHKLMNPRSTVWYZbdfhkmrstvwz23579';
	public const ALPHABET_URL_SAFE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

	private readonly string $alphabet;
	private readonly int $alphabetSize;
	private readonly int $threshold;
	private readonly int $defaultLength;

	public function __construct(
		string $alphabet = self::ALPHABET_WORD_SAFE,
		int $defaultLength = 13,
	) {
		$size = strlen($alphabet);

		if ($size < 2) {
			throw new InvalidArgumentException('Alphabet must contain at least 2 characters');
		}
		if ($size > 256) {
			throw new InvalidArgumentException('Alphabet must contain at most 256 characters');
		}
		if (count(array_unique(str_split($alphabet))) !== $size) {
			throw new InvalidArgumentException('Alphabet must not contain duplicate characters');
		}
		if ($defaultLength < 1) {
			throw new InvalidArgumentException('Default length must be >= 1');
		}

		$this->alphabet = $alphabet;
		$this->alphabetSize = $size;
		// Largest multiple of $size that fits in one byte (0–255).
		// Values at or above $threshold are discarded → no modulo bias.
		$this->threshold = intdiv(256, $size) * $size;
		$this->defaultLength = $defaultLength;
	}

	public function generate(?int $length = null): string
	{
		$length ??= $this->defaultLength;

		if ($length < 1) {
			throw new InvalidArgumentException('Length must be >= 1');
		}

		// If the alphabet size is a divisor of 256 (2, 4, 8, 16, 32, 64, 128, 256),
		// there is no bias and we can use the fast path without rejection sampling.
		if ($this->threshold === 256) {
			return $this->generateFast($length);
		}

		return $this->generateRejection($length);
	}

	private function generateFast(int $length): string
	{
		$bytes = random_bytes($length);
		$id = '';
		for ($i = 0; $i < $length; $i++) {
			$id .= $this->alphabet[ord($bytes[$i]) % $this->alphabetSize];
		}
		return $id;
	}

	private function generateRejection(int $length): string
	{
		// Expected discard rate: 1 - threshold/256. We fetch slightly more bytes than
		// needed so that in most cases a single random_bytes() call suffices.
		$acceptRate = $this->threshold / 256;
		$batchSize = (int) ceil(($length / $acceptRate) * 1.1);

		$id = '';
		$needed = $length;

		while ($needed > 0) {
			$bytes = random_bytes($batchSize);
			$len = strlen($bytes);

			for ($i = 0; $i < $len && $needed > 0; $i++) {
				$b = ord($bytes[$i]);
				if ($b >= $this->threshold) {
					continue;
				}
				$id .= $this->alphabet[$b % $this->alphabetSize];
				$needed--;
			}

			// If we still need more, request smaller follow-up batches.
			$batchSize = (int) ceil(($needed / $acceptRate) * 1.1);
		}

		return $id;
	}
}

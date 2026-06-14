<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\Uid;
use InvalidArgumentException;

final class UidTest extends TestCase
{
	public function testGeneratesConfiguredDefaultLength(): void
	{
		$id = new Uid(Uid::ALPHABET_WORD_SAFE, 13)->generate();

		$this->assertSame(13, strlen($id));
	}

	public function testUsesConfiguredAlphabet(): void
	{
		$id = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13)->generate();

		$this->assertMatchesRegularExpression('/^[123456789bcdfghklmnpqrstvwxyz]{13}$/', $id);
	}

	public function testOverridesLength(): void
	{
		$id = new Uid(Uid::ALPHABET_WORD_SAFE, 13)->generate(5);

		$this->assertSame(5, strlen($id));
	}

	public function testGeneratesDifferentIds(): void
	{
		$uid = new Uid(Uid::ALPHABET_WORD_SAFE, 13);

		$this->assertNotSame($uid->generate(), $uid->generate());
	}

	public function testSupportsRejectionSampling(): void
	{
		$alphabet = '';

		for ($i = 0; $i < 129; $i++) {
			$alphabet .= chr($i);
		}

		$id = new Uid($alphabet, 13)->generate(64);

		$this->assertSame(64, strlen($id));
	}

	public function testRejectsShortAlphabet(): void
	{
		$this->throws(InvalidArgumentException::class, 'Alphabet must contain at least 2 characters');

		new Uid('a', 13);
	}

	public function testRejectsLongAlphabet(): void
	{
		$this->throws(InvalidArgumentException::class, 'Alphabet must contain at most 256 characters');

		new Uid(str_repeat('a', 257), 13);
	}

	public function testRejectsDuplicateAlphabet(): void
	{
		$this->throws(InvalidArgumentException::class, 'Alphabet must not contain duplicate characters');

		new Uid('abca', 13);
	}

	public function testRejectsInvalidDefaultLength(): void
	{
		$this->throws(InvalidArgumentException::class, 'Default length must be >= 1');

		new Uid(Uid::ALPHABET_WORD_SAFE, 0);
	}

	public function testRejectsInvalidLength(): void
	{
		$this->throws(InvalidArgumentException::class, 'Length must be >= 1');

		new Uid(Uid::ALPHABET_WORD_SAFE, 13)->generate(0);
	}
}

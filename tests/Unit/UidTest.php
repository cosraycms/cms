<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\Uid;
use InvalidArgumentException;

final class UidTest extends TestCase
{
	public function testGeneratesDefaultLength(): void
	{
		$id = new Uid()->generate();

		$this->assertSame(13, strlen($id));
	}

	public function testUsesConfiguredAlphabet(): void
	{
		$id = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE)->generate();

		$this->assertMatchesRegularExpression('/^[123456789bcdfghklmnpqrstvwxyz]{13}$/', $id);
	}

	public function testOverridesLength(): void
	{
		$id = new Uid()->generate(5);

		$this->assertSame(5, strlen($id));
	}

	public function testGeneratesDifferentIds(): void
	{
		$uid = new Uid();

		$this->assertNotSame($uid->generate(), $uid->generate());
	}

	public function testSupportsRejectionSampling(): void
	{
		$alphabet = '';

		for ($i = 0; $i < 129; $i++) {
			$alphabet .= chr($i);
		}

		$id = new Uid($alphabet)->generate(64);

		$this->assertSame(64, strlen($id));
	}

	public function testRejectsShortAlphabet(): void
	{
		$this->throws(InvalidArgumentException::class, 'Alphabet must contain at least 2 characters');

		new Uid('a');
	}

	public function testRejectsLongAlphabet(): void
	{
		$this->throws(InvalidArgumentException::class, 'Alphabet must contain at most 256 characters');

		new Uid(str_repeat('a', 257));
	}

	public function testRejectsDuplicateAlphabet(): void
	{
		$this->throws(InvalidArgumentException::class, 'Alphabet must not contain duplicate characters');

		new Uid('abca');
	}

	public function testRejectsInvalidDefaultLength(): void
	{
		$this->throws(InvalidArgumentException::class, 'Default length must be >= 1');

		new Uid(defaultLength: 0);
	}

	public function testRejectsInvalidLength(): void
	{
		$this->throws(InvalidArgumentException::class, 'Length must be >= 1');

		new Uid()->generate(0);
	}
}

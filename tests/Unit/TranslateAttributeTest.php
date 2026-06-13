<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;
use Cosray\Tests\TestCase;

final class TranslateAttributeTest extends TestCase
{
	public function testTranslateDefaultsToSymmetricMode(): void
	{
		$translate = new Translate();

		$this->assertSame(TranslateMode::Symmetric, $translate->mode);
	}

	public function testTranslateAcceptsAsymmetricMode(): void
	{
		$translate = new Translate(TranslateMode::Asymmetric);

		$this->assertSame(TranslateMode::Asymmetric, $translate->mode);
	}
}

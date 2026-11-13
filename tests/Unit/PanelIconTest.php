<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\Icon;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PanelIconTest extends TestCase
{
	public function testRendersADecorativeScalableIcon(): void
	{
		$this->assertHtmlNodeExists(
			'//svg[@aria-hidden="true" and @focusable="false" and @fill="currentColor" and @viewbox="0 0 16 16"]',
			Icon::render('plus'),
		);
	}

	public function testCarriesThePanelIconClassAndAPartClass(): void
	{
		$this->assertHtmlNodeExists('//svg[@class="cms-icon"]', Icon::render('plus'));
		$this->assertHtmlNodeExists(
			'//svg[@class="cms-icon cms-field-width-expand"]',
			Icon::render('plus', 'cms-field-width-expand'),
		);
	}

	#[DataProvider('untrustedNames')]
	public function testRejectsPathsMarkupAndProviderIds(string $name): void
	{
		$this->assertSame('', Icon::render($name));
	}

	public static function untrustedNames(): array
	{
		return [
			['../icons/plus'],
			['/etc/passwd'],
			['plus" onload="alert(1)'],
			['bi:plus'],
			['not-a-bundled-icon'],
			["plus\0"],
		];
	}
}

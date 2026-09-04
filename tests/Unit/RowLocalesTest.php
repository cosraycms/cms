<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\RowLocales;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Panel\RowLocales
 */
final class RowLocalesTest extends TestCase
{
	/** @param list<array<string, mixed>> $fields */
	private function type(array $fields): array
	{
		return ['fields' => $fields];
	}

	/** @param array<string, mixed> $extra */
	private function field(string $control, bool $translate = true, array $extra = []): array
	{
		return ['control' => ['name' => $control], 'translate' => $translate] + $extra;
	}

	public function testRowOwnsTheTabsOfATranslatedPrimitive(): void
	{
		$this->assertTrue(RowLocales::owned($this->type([$this->field('textarea')]), 2));
	}

	public function testRowOwnsTheTabsOfATranslatedElementControl(): void
	{
		$this->assertTrue(RowLocales::owned($this->type([$this->field('element')]), 2));
	}

	public function testOneLocaleHasNothingToSwitch(): void
	{
		$this->assertFalse(RowLocales::owned($this->type([$this->field('text')]), 1));
	}

	public function testUntranslatedFieldsAreIgnored(): void
	{
		$this->assertFalse(RowLocales::owned($this->type([$this->field('text', false)]), 2));
	}

	public function testControlsWithoutPerLocaleVariantsAreIgnored(): void
	{
		// Translated, but rendered once — the wrapper shows no tabs either.
		$this->assertFalse(RowLocales::owned($this->type([$this->field('checkbox')]), 2));
	}

	public function testHiddenFieldsAreIgnored(): void
	{
		$type = $this->type([$this->field('text', true, ['hidden' => true])]);

		$this->assertFalse(RowLocales::owned($type, 2));
	}

	public function testOneSwitchableFieldAmongOthersIsEnough(): void
	{
		$type = $this->type([
			$this->field('checkbox'),
			'not a field',
			$this->field('text'),
		]);

		$this->assertTrue(RowLocales::owned($type, 2));
	}

	public function testATypeWithoutFieldsOwnsNothing(): void
	{
		$this->assertFalse(RowLocales::owned([], 2));
	}
}

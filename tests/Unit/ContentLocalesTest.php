<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\ContentLocales;
use Cosray\Tests\TestCase;

/** @covers \Cosray\Panel\ContentLocales */
final class ContentLocalesTest extends TestCase
{
	public function testTranslatedPrimitiveUsesTheNodeSelector(): void
	{
		$this->assertTrue(ContentLocales::used([$this->field('text')], 2));
	}

	public function testTranslatedFieldNestedInEntriesUsesTheNodeSelector(): void
	{
		$this->assertTrue(ContentLocales::used([
			$this->field('entries', false, [
				'props' => ['entryTypes' => [['fields' => [$this->field('element')]]]],
			]),
		], 2));
	}

	public function testTranslatedFieldNestedInSymmetricBlocksUsesTheNodeSelector(): void
	{
		$this->assertTrue(ContentLocales::used([
			$this->field('blocks', true, [
				'props' => ['blockTypes' => [['fields' => [$this->field('textarea')]]]],
			]),
		], 2));
	}

	public function testAsymmetricBlocksUseTheNodeSelector(): void
	{
		$this->assertTrue(ContentLocales::used([
			$this->field('blocks', true, [], ['translateMode' => 'asymmetric']),
		], 2));
	}

	public function testNeutralAndSingleLanguageContentNeedNoSelector(): void
	{
		$this->assertFalse(ContentLocales::used([$this->field('text', false)], 2));
		$this->assertFalse(ContentLocales::used([$this->field('text')], 1));
	}

	public function testHiddenTranslatedFieldsAreIgnored(): void
	{
		$this->assertFalse(ContentLocales::used([
			$this->field('text', true, [], ['hidden' => true]),
		], 2));
	}

	/**
	 * @param array<string, mixed> $props
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function field(string $control, bool $translate = true, array $props = [], array $extra = []): array
	{
		return [
			'control' => ['name' => $control, ...$props],
			'translate' => $translate,
			...$extra,
		];
	}
}

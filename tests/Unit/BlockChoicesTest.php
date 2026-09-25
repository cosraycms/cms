<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Block\Registry;
use Cosray\Exception\RuntimeException;
use Cosray\Panel\BlockChoices;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class BlockChoicesTest extends TestCase
{
	public function testOlderKeyedDescriptorsKeepAllTypesAndStableDefaults(): void
	{
		$types = [];
		foreach (Registry::withDefaults()->all() as $type) {
			$types[$type] = ['type' => $type];
		}
		$choices = new BlockChoices('content', ['blockTypes' => $types], static function (): never {
			throw new \LogicException('Bundled icons must not call a provider');
		});
		$this->assertSame(array_keys($types), array_keys($choices->all));
		$this->assertSame(array_slice(array_keys($types), 0, 5), array_column($choices->common, 'type'));
		foreach ($choices->all as $choice) {
			$this->assertStringContainsString('<svg', $choice['icon']);
			$this->assertNotEmpty($choice['label']);
		}
		$this->assertSame([], new BlockChoices('content', [])->all);
	}

	public function testCommonChoicesReuseProviderResultsWithoutChangingPermissionData(): void
	{
		$calls = [];
		$custom = ['id' => 'app:note', 'args' => ['size' => 20]];
		$types = [
			['type' => 'custom', 'handle' => 'note', 'label' => 'A <note>', 'icon' => $custom],
			['type' => 'missing', 'icon' => ['id' => 'missing']],
			['type' => 'plain'],
		];
		$choices = new BlockChoices(
			'content',
			[
				'blockTypes' => $types,
				'commonTypes' => ['missing', 'custom', 'custom'],
			],
			static function (array $icon) use (&$calls): string {
				$calls[] = $icon;
				return $icon['id'] === 'app:note' ? '<svg viewBox="0 0 16 16"></svg>' : '<!-- icon not found -->';
			},
		);
		$this->assertSame([$custom, ['id' => 'missing']], $calls);
		$this->assertSame(['missing', 'custom'], array_column($choices->common, 'type'));
		$this->assertSame($choices->all['custom'], $choices->common[1]);
		$this->assertSame($types, array_values($choices->types));
		$this->assertSame('A <note>', $choices->all['custom']['label']);
		$this->assertStringContainsString('<svg', $choices->all['missing']['icon']);
		$this->assertSame($choices->all['plain']['icon'], $choices->all['missing']['icon']);
		$this->assertSame(
			['custom', 'missing', 'plain'],
			array_column(new BlockChoices('content', ['blockTypes' => $types, 'commonTypes' => []])->common, 'type'),
		);
	}

	public static function malformed(): array
	{
		$types = array_map(static fn(int $i): array => ['type' => (string) $i], range(1, 7));
		return [
			[['blockTypes' => 'bad'], "blockTypes must be a list of block type descriptors, 'bad' given"],
			[['blockTypes' => ['bad']], "block type descriptor 0 needs a non-empty string 'type', 'bad' given"],
			[['blockTypes' => [['type' => '']]], "block type descriptor 0 needs a non-empty string 'type', '' given"],
			[
				['blockTypes' => $types, 'commonTypes' => null],
				'commonTypes must be a list of block type IDs, null given',
			],
			[
				['blockTypes' => $types, 'commonTypes' => 'bad'],
				"commonTypes must be a list of block type IDs, 'bad' given",
			],
			[
				['blockTypes' => $types, 'commonTypes' => ['a' => '1']],
				'commonTypes must be a list of block type IDs, keyed array given',
			],
			[['blockTypes' => $types, 'commonTypes' => [1]], 'common type 0 must be a block type ID, 1 given'],
			[['blockTypes' => $types, 'commonTypes' => ['missing']], "common type 'missing' is not allowed"],
			[
				['blockTypes' => $types, 'commonTypes' => array_column($types, 'type')],
				'may have at most 5 distinct common types',
			],
		];
	}

	#[DataProvider('malformed')]
	public function testMalformedSelectionsFailNamingTheFieldAndEntry(array $props, string $message): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Blocks field 'content' {$message}");
		new BlockChoices('content', $props);
	}
}

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
		$this->assertSame(array_slice(array_keys($types), 0, 6), array_column($choices->common, 'type'));
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
			[['blockTypes' => 'bad']],
			[['blockTypes' => ['bad']]],
			[['blockTypes' => [['type' => '']]]],
			[['blockTypes' => $types, 'commonTypes' => null]],
			[['blockTypes' => $types, 'commonTypes' => 'bad']],
			[['blockTypes' => $types, 'commonTypes' => ['a' => '1']]],
			[['blockTypes' => $types, 'commonTypes' => [1]]],
			[['blockTypes' => $types, 'commonTypes' => ['missing']]],
			[['blockTypes' => $types, 'commonTypes' => array_column($types, 'type')]],
		];
	}

	#[DataProvider('malformed')]
	public function testMalformedSelectionsAreNotTreatedAsPermissions(array $props): void
	{
		$this->expectException(RuntimeException::class);
		new BlockChoices('content', $props);
	}
}

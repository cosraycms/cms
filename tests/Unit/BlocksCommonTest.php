<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Block;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Schema\CommonHandler;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Schema\Allows;
use Cosray\Schema\Common;
use Cosray\Tests\Fixtures\Block\CatalogBlock;
use Cosray\Tests\RichtextOwnerTestCase;
use Cosray\Value\ValueContext;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

final class CommonFields
{
	#[Common(CatalogBlock::class), Allows(Block\Text::class, CatalogBlock::class)]
	public Blocks $before;

	#[Allows(Block\Text::class, CatalogBlock::class), Common(CatalogBlock::class)]
	public Blocks $after;

	#[Common(Block\Iframe::class)]
	public Blocks $defaults;
}

final class BlocksCommonTest extends RichtextOwnerTestCase
{
	private function field(?string $property = null, ?Services $services = null): Blocks
	{
		$field = new Blocks('content', $this->owner(), new ValueContext('content', []));
		$field->init(
			$services ?? Services::withDefaults(),
			$property === null ? null : new ReflectionProperty(CommonFields::class, $property),
		);

		return $field;
	}

	public function testDefaultsUseAllowedOrderIncludingEmptyAndSmallCatalogs(): void
	{
		foreach ([0, 1, 4, 6, 8] as $count) {
			$registry = new Block\Registry();
			$types = array_slice(Block\Registry::withDefaults()->all(), 0, $count);
			foreach ($types as $type) {
				$registry->register($type);
			}
			$field = $this->field(
				services: new Services(
					\Cosray\Field\Schema\Registry::withDefaults(),
					new \Cosray\Node\Types(),
					$registry,
				),
			);
			$props = $field->control()->array()['props'];
			$this->assertSame(array_slice($types, 0, 6), $props['commonTypes']);
			$this->assertSame($types, array_column($props['blockTypes'], 'type'));
		}
	}

	public function testCommonReplacesDeduplicatesAndResetsWithoutChangingPermissions(): void
	{
		$field = $this
			->field()
			->allow(Block\Text::class, Block\Heading::class)
			->common(Block\Heading::class, Block\Heading::class, Block\Text::class);
		$this->assertSame(
			[Block\Heading::class, Block\Text::class],
			$field->control()->array()['props']['commonTypes'],
		);
		$field->common(Block\Heading::class);
		$this->assertSame([Block\Heading::class], $field->control()->array()['props']['commonTypes']);
		$this->assertSame([Block\Text::class, Block\Heading::class], $field->allowedBlockTypes());
		$this->assertTrue($field->allows(Block\Text::class));
		$row = [
			'uid' => 'row1',
			'type' => Block\Text::class,
			'layout' => ['span' => 1, 'rows' => 1, 'indent' => 0],
			'fields' => ['text' => ['type' => \Cosray\Field\Textarea::class, 'value' => ['zxx' => 'Not common']]],
		];
		$this->assertTrue(
			$field
				->shape()
				->validate(['type' => Blocks::class, 'value' => ['zxx' => [$row]]])
				->valid(),
		);
		$row['type'] = Block\Iframe::class;
		$this->assertFalse(
			$field
				->shape()
				->validate(['type' => Blocks::class, 'value' => ['zxx' => [$row]]])
				->valid(),
		);
		$field->common();
		$this->assertSame($field->allowedBlockTypes(), $field->control()->array()['props']['commonTypes']);
	}

	public function testAttributeOrderAndRegistryDefaultsResolveAtControlTime(): void
	{
		foreach (['before', 'after'] as $property) {
			$this->assertSame(
				[CatalogBlock::class],
				$this->field($property)->control()->array()['props']['commonTypes'],
			);
		}
		$this->assertSame([Block\Iframe::class], $this->field('defaults')->control()->array()['props']['commonTypes']);
		$field = $this->field()->common(CatalogBlock::class)->allow(CatalogBlock::class);
		$this->assertSame([CatalogBlock::class], $field->control()->array()['props']['commonTypes']);
	}

	public static function invalidCommonTypes(): array
	{
		return [
			'unknown' => [['App\\MissingBlock'], 'must be a class implementing'],
			'non-block' => [[Text::class], 'must be a class implementing'],
			'disallowed' => [[CatalogBlock::class], 'is not allowed'],
			'oversized' => [array_slice(Block\Registry::withDefaults()->all(), 0, 7), 'at most 6 distinct'],
		];
	}

	#[DataProvider('invalidCommonTypes')]
	public function testInvalidCommonConfigurationFailsClearly(array $types, string $message): void
	{
		$field = $this->field()->common(...$types);
		$this->throws(RuntimeException::class, $message);
		$field->control();
	}

	public function testCommonRequiresBlocks(): void
	{
		$field = new Text('title', $this->owner(), new ValueContext('title', []));
		$handler = new CommonHandler();
		$this->assertSame([], $handler->properties(new Common(), $field));
		$this->throws(RuntimeException::class, 'Cosray\\Field\\Blocks');
		$handler->apply(new Common(), $field);
	}

	public function testCustomIconAndInterfaceTranslationReachTheCompleteDescriptor(): void
	{
		Verba::activate(new Translator('de', ['cosray' => self::root() . '/lang']));
		try {
			$props = $this->field('before')->control()->array()['props'];
		} finally {
			Verba::deactivate();
		}
		$this->assertSame('Überschrift', $props['blockTypes'][1]['label']);
		$this->assertSame('catalog-note', $props['blockTypes'][1]['handle']);
		$this->assertSame(['id' => 'test:note', 'args' => ['size' => 20]], $props['blockTypes'][1]['icon']);
		$this->assertNull($props['blockTypes'][0]['icon']);
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Image;
use Cosray\Field\Index;
use Cosray\Field\Text;
use Cosray\Migration\NodeContentNormalizer;
use Cosray\Tests\Fixtures\Field\TestMoney;
use Cosray\Tests\TestCase;
use Cosray\Uid;
use stdClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class FieldIndexTest extends TestCase
{
	public function testDefaultsResolveAliases(): void
	{
		$index = Index::withDefaults();

		$this->assertSame(Text::class, $index->resolve('text'));
		$this->assertSame(Blocks::class, $index->resolve('grid'));
		$this->assertSame(Image::class, $index->resolve('picture'));
	}

	public function testResolvePassesFieldClassesThrough(): void
	{
		$index = new Index();

		$this->assertSame(Text::class, $index->resolve(Text::class));
	}

	public function testResolveRejectsUnknownTypes(): void
	{
		$index = Index::withDefaults();

		$this->assertNull($index->resolve('nonexistent'));
		$this->assertNull($index->resolve(stdClass::class));
	}

	public function testAddRejectsNonFieldClasses(): void
	{
		$this->throws(RuntimeException::class);

		new Index()->add(stdClass::class);
	}

	public function testCustomFieldTypeWithAlias(): void
	{
		$index = Index::withDefaults();
		$index->add(TestMoney::class, 'money');

		$this->assertSame(TestMoney::class, $index->resolve('money'));
		$this->assertContains(TestMoney::class, $index->all());
	}

	public function testNormalizerUsesIndexAliases(): void
	{
		$index = Index::withDefaults();
		$index->add(TestMoney::class, 'money');
		$normalizer = new NodeContentNormalizer(new Uid('ab', 4), $index);

		$result = $normalizer->normalize([
			'price' => ['type' => 'money', 'value' => '9.99'],
		]);

		$this->assertSame(TestMoney::class, $result['price']['type']);
	}
}

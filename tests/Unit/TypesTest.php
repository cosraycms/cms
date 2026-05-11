<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Exception\NoSuchProperty;
use Celemas\Cms\Node\Schema\Registry;
use Celemas\Cms\Node\Types;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithChildrenAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithHandleAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithIconAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithNameAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithPermissionAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithRenderAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithRouteAttribute;
use Celemas\Cms\Tests\Fixtures\Node\PlainBlock;
use Celemas\Cms\Tests\Fixtures\Node\PlainPage;
use Celemas\Cms\Tests\TestCase;

final class TypesTest extends TestCase
{
	private Types $types;

	protected function setUp(): void
	{
		parent::setUp();
		$this->types = new Types();
	}

	public function testLabelAttributeSet(): void
	{
		$this->assertEquals('NodeWithHandleAttribute', $this->types->get(
			NodeWithHandleAttribute::class,
			'label',
		));
		$this->assertEquals('Node With Custom Name Attribute', $this->types->get(
			NodeWithNameAttribute::class,
			'label',
		));
	}

	public function testHandleAttributeSet(): void
	{
		$this->assertEquals('node-with-name-attribute', $this->types->get(
			NodeWithNameAttribute::class,
			'handle',
		));
		$this->assertEquals('node-with-custom-handle-attribute', $this->types->get(
			NodeWithHandleAttribute::class,
			'handle',
		));
	}

	public function testRouteAttributeSet(): void
	{
		$this->assertEquals('', $this->types->get(NodeWithNameAttribute::class, 'route'));
		$this->assertEquals('/node-with-custom/{route}', $this->types->get(
			NodeWithRouteAttribute::class,
			'route',
		));
	}

	public function testRoutableIsDerivedFromRouteAttribute(): void
	{
		$this->assertFalse((bool) $this->types->get(NodeWithNameAttribute::class, 'routable', false));
		$this->assertTrue((bool) $this->types->get(NodeWithRouteAttribute::class, 'routable', false));
	}

	public function testRenderAttributeSet(): void
	{
		$this->assertEquals(
			'node-with-name-attribute',
			$this->types->schemaOf(NodeWithNameAttribute::class)->renderer,
		);
		$this->assertEquals(
			'template-defined-by-render-attribute',
			$this->types->schemaOf(NodeWithRenderAttribute::class)->renderer,
		);
	}

	public function testRenderableRequiresRenderOrRouteAttribute(): void
	{
		$this->assertFalse((bool) $this->types->get(NodeWithNameAttribute::class, 'renderable', false));
		$this->assertTrue((bool) $this->types->get(NodeWithRenderAttribute::class, 'renderable', false));
	}

	public function testPermissionAttributeSet(): void
	{
		$this->assertEquals(
			[
				'read' => 'everyone',
				'create' => 'authenticated',
				'change' => 'authenticated',
				'delete' => 'authenticated',
			],
			$this->types->schemaOf(NodeWithNameAttribute::class)->permission,
		);
		$this->assertEquals(
			[
				'read' => 'me',
			],
			$this->types->schemaOf(NodeWithPermissionAttribute::class)->permission,
		);
	}

	public function testChildrenAttributeSet(): void
	{
		$this->assertSame(
			[
				PlainPage::class,
				PlainBlock::class,
			],
			$this->types->get(NodeWithChildrenAttribute::class, 'children'),
		);
		$this->assertSame([], $this->types->get(NodeWithNameAttribute::class, 'children'));
	}

	public function testIconAttributeSet(): void
	{
		$this->assertSame(
			[
				'id' => 'bi:check',
				'args' => [
					'color' => '#ff0000',
					'class' => 'cms-node-icon',
					'style' => 'height: 1rem',
				],
			],
			$this->types->get(NodeWithIconAttribute::class, 'icon'),
		);
		$this->assertNull($this->types->get(NodeWithNameAttribute::class, 'icon'));
	}

	public function testSchemaMagicAccessCoversAllBuiltinKeys(): void
	{
		$registry = Registry::withDefaults();
		$types = new Types($registry);
		$schema = $types->schemaOf(PlainPage::class);

		foreach ($registry->defaultKeys() as $key) {
			$this->assertArrayHasKey($key, $schema->properties());
			$this->assertSame($schema->get($key), $schema->{$key});
		}

		$this->assertFalse(isset($schema->missing));
		$this->assertNull($schema->get('missing'));
		$this->assertSame('fallback', $schema->get('missing', 'fallback'));

		$this->throws(
			NoSuchProperty::class,
			"The node schema '" . PlainPage::class . "' doesn't have the property 'missing'",
		);
		$schema->missing;
	}

	public function testTypeMagicAccessCoversAllBuiltinKeys(): void
	{
		$registry = Registry::withDefaults();
		$types = new Types($registry);
		$type = $types->typeOf(PlainPage::class);

		foreach ($registry->defaultKeys() as $key) {
			$this->assertArrayHasKey($key, $type->all());
			$this->assertSame($type->get($key), $type->{$key});
		}

		$this->assertSame(PlainPage::class, $type->class);
		$this->assertSame('PlainPage', $type->classname);
		$this->assertFalse(isset($type->missing));
		$this->assertNull($type->get('missing'));
		$this->assertSame('fallback', $type->get('missing', 'fallback'));

		$this->throws(
			NoSuchProperty::class,
			"The node type '" . PlainPage::class . "' doesn't have the property 'missing'",
		);
		$type->missing;
	}

	public function testMagicIssetIsFalseForNullBuiltinValues(): void
	{
		$schema = $this->types->schemaOf(NodeWithNameAttribute::class);
		$type = $this->types->typeOf(NodeWithNameAttribute::class);

		$this->assertFalse(isset($schema->titleField));
		$this->assertFalse(isset($type->titleField));

		$this->assertFalse($this->types->typeOf(PlainBlock::class)->deletable);
	}
}

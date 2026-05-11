<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Node\Schema;
use Celemas\Cms\Node\Schema\ChildrenHandler;
use Celemas\Cms\Node\Schema\DeletableHandler;
use Celemas\Cms\Node\Schema\FieldOrderHandler;
use Celemas\Cms\Node\Schema\HandleHandler;
use Celemas\Cms\Node\Schema\IconHandler;
use Celemas\Cms\Node\Schema\LabelHandler;
use Celemas\Cms\Node\Schema\PermissionHandler;
use Celemas\Cms\Node\Schema\Registry;
use Celemas\Cms\Node\Schema\RenderHandler;
use Celemas\Cms\Node\Schema\RouteHandler;
use Celemas\Cms\Node\Schema\TitleHandler;
use Celemas\Cms\Node\Types;
use Celemas\Cms\Schema\Children;
use Celemas\Cms\Schema\Deletable;
use Celemas\Cms\Schema\FieldOrder;
use Celemas\Cms\Schema\Handle;
use Celemas\Cms\Schema\Icon;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Permission;
use Celemas\Cms\Schema\Render;
use Celemas\Cms\Schema\Route;
use Celemas\Cms\Schema\Title;
use Celemas\Cms\Tests\Fixtures\Node\CustomIcon;
use Celemas\Cms\Tests\Fixtures\Node\CustomIconHandler;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithChildrenAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithCustomAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithHandleAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithIconAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithInvalidPropertyTitleAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithNameAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithPropertyTitleAttribute;
use Celemas\Cms\Tests\Fixtures\Node\NodeWithRouteAttribute;
use Celemas\Cms\Tests\Fixtures\Node\PlainBlock;
use Celemas\Cms\Tests\Fixtures\Node\PlainPage;
use Celemas\Cms\Tests\TestCase;
use ValueError;

final class NodeSchemaRegistryTest extends TestCase
{
	// -- Registry basics -------------------------------------------------------

	public function testRegistryWithDefaultsContainsAllBuiltinHandlers(): void
	{
		$registry = Registry::withDefaults();

		$this->assertInstanceOf(HandleHandler::class, $registry->getHandler(new Handle('test')));
		$this->assertInstanceOf(LabelHandler::class, $registry->getHandler(new Label('test')));
		$this->assertInstanceOf(
			IconHandler::class,
			$registry->getHandler(new Icon('bi:check')),
		);
		$this->assertInstanceOf(RouteHandler::class, $registry->getHandler(new Route('/test')));
		$this->assertInstanceOf(RenderHandler::class, $registry->getHandler(new Render('test')));
		$this->assertInstanceOf(PermissionHandler::class, $registry->getHandler(new Permission('test')));
		$this->assertInstanceOf(TitleHandler::class, $registry->getHandler(new Title('test')));
		$this->assertInstanceOf(
			FieldOrderHandler::class,
			$registry->getHandler(new FieldOrder('a', 'b')),
		);
		$this->assertInstanceOf(DeletableHandler::class, $registry->getHandler(new Deletable()));
		$this->assertInstanceOf(
			ChildrenHandler::class,
			$registry->getHandler(new Children(PlainPage::class, PlainBlock::class)),
		);
	}

	public function testRegistryReturnsNullForUnknownAttribute(): void
	{
		$registry = Registry::withDefaults();

		$this->assertNull($registry->getHandler(new CustomIcon('star')));
	}

	public function testRegistryAcceptsCustomHandler(): void
	{
		$registry = Registry::withDefaults();
		$registry->register(CustomIcon::class, new CustomIconHandler());

		$this->assertInstanceOf(CustomIconHandler::class, $registry->getHandler(new CustomIcon('star')));
	}

	// -- Individual handler resolve() -----------------------------------------

	public function testHandleHandlerResolve(): void
	{
		$handler = new HandleHandler();
		$result = $handler->resolve(new Handle('my-handle'), PlainPage::class);

		$this->assertEquals(['handle' => 'my-handle'], $result);
	}

	public function testLabelHandlerResolve(): void
	{
		$handler = new LabelHandler();
		$result = $handler->resolve(new Label('My Label'), PlainPage::class);

		$this->assertEquals(['label' => 'My Label'], $result);
	}

	public function testIconHandlerResolve(): void
	{
		$handler = new IconHandler();
		$result = $handler->resolve(new Icon('bi:check', ['color' => '#ff0000']), PlainPage::class);

		$this->assertEquals(
			[
				'icon' => [
					'id' => 'bi:check',
					'args' => ['color' => '#ff0000'],
				],
			],
			$result,
		);
	}

	public function testIconAcceptsNamedArguments(): void
	{
		$icon = new Icon('bi:check', width: '1rem', color: '#f00');

		$this->assertSame(
			[
				'width' => '1rem',
				'color' => '#f00',
			],
			$icon->args,
		);
	}

	public function testIconRejectsPositionalArguments(): void
	{
		$this->throws(
			ValueError::class,
			'Icon arguments must be an associative array or named arguments',
		);

		new Icon('bi:check', 'width');
	}

	public function testIconRejectsListArrayArguments(): void
	{
		$this->throws(
			ValueError::class,
			'Icon arguments must be an associative array or named arguments',
		);

		new Icon('bi:check', ['width', 'color']);
	}

	public function testRouteHandlerResolve(): void
	{
		$handler = new RouteHandler();
		$result = $handler->resolve(new Route('/article/{uid}'), PlainPage::class);

		$this->assertEquals(
			[
				'route' => '/article/{uid}',
				'routable' => true,
			],
			$result,
		);
	}

	public function testRenderHandlerResolve(): void
	{
		$handler = new RenderHandler();
		$result = $handler->resolve(new Render('my-template'), PlainPage::class);

		$this->assertEquals(
			[
				'renderer' => 'my-template',
				'renderable' => true,
			],
			$result,
		);
	}

	public function testPermissionHandlerResolve(): void
	{
		$handler = new PermissionHandler();
		$result = $handler->resolve(new Permission(['read' => 'me']), PlainPage::class);

		$this->assertEquals(['permission' => ['read' => 'me']], $result);
	}

	public function testTitleHandlerResolve(): void
	{
		$handler = new TitleHandler();
		$result = $handler->resolve(new Title('heading'), PlainPage::class);

		$this->assertEquals(['titleField' => 'heading'], $result);
	}

	public function testTitleHandlerThrowsForEmptyClassLevelField(): void
	{
		$handler = new TitleHandler();

		$this->throws(
			RuntimeException::class,
			"The #[Title] attribute on node 'Celemas\\Cms\\Tests\\Fixtures\\Node\\PlainPage' requires a non-empty field name when used on a class.",
		);
		$handler->resolve(new Title(), PlainPage::class);
	}

	public function testFieldOrderHandlerResolve(): void
	{
		$handler = new FieldOrderHandler();
		$result = $handler->resolve(new FieldOrder('a', 'b', 'c'), PlainPage::class);

		$this->assertEquals(['fieldOrder' => ['a', 'b', 'c']], $result);
	}

	public function testDeletableHandlerResolve(): void
	{
		$handler = new DeletableHandler();
		$result = $handler->resolve(new Deletable(false), PlainPage::class);

		$this->assertEquals(['deletable' => false], $result);
	}

	public function testChildrenHandlerResolve(): void
	{
		$handler = new ChildrenHandler();
		$result = $handler->resolve(new Children(PlainPage::class, PlainBlock::class), PlainPage::class);

		$this->assertEquals(
			[
				'children' => [
					PlainPage::class,
					PlainBlock::class,
				],
			],
			$result,
		);
	}

	// -- Schema integration with Registry -------------------------------------

	public function testSchemaResolvesBuiltinAttributesViaRegistry(): void
	{
		$registry = Registry::withDefaults();
		$schema = new Schema(PlainPage::class, $registry);

		$this->assertEquals('Plain Page', $schema->label);
		$this->assertEquals('/plain-page/{uid}', $schema->route);
		$this->assertTrue($schema->routable);
		$this->assertEquals('heading', $schema->titleField);
		$this->assertEquals(['heading', 'body'], $schema->fieldOrder);
	}

	public function testSchemaAppliesDefaultsWhenNoAttributes(): void
	{
		$registry = Registry::withDefaults();
		$schema = new Schema(NodeWithRouteAttribute::class, $registry);

		// No #[Label] => fallback to class name
		$this->assertEquals('NodeWithRouteAttribute', $schema->label);
		// No #[Handle] => fallback to kebab-case
		$this->assertEquals('node-with-route-attribute', $schema->handle);
		// No #[Permission] => default permission map
		$this->assertEquals(
			[
				'read' => 'everyone',
				'create' => 'authenticated',
				'change' => 'authenticated',
				'delete' => 'authenticated',
			],
			$schema->permission,
		);
		// No #[Title] => null
		$this->assertNull($schema->titleField);
		// No #[FieldOrder] => null
		$this->assertNull($schema->fieldOrder);
		// No #[Icon] => null
		$this->assertNull($schema->icon);
		// No #[Deletable] => true
		$this->assertTrue($schema->deletable);
		// No #[Children] => []
		$this->assertSame([], $schema->children);
	}

	public function testSchemaResolvesChildrenAttributeFromClass(): void
	{
		$registry = Registry::withDefaults();
		$schema = new Schema(NodeWithChildrenAttribute::class, $registry);

		$this->assertSame(
			[
				PlainPage::class,
				PlainBlock::class,
			],
			$schema->children,
		);
	}

	public function testSchemaResolvesIconAttributeFromClass(): void
	{
		$registry = Registry::withDefaults();
		$schema = new Schema(NodeWithIconAttribute::class, $registry);

		$this->assertSame(
			[
				'id' => 'bi:check',
				'args' => [
					'color' => '#ff0000',
					'class' => 'cms-node-icon',
					'style' => 'height: 1rem',
				],
			],
			$schema->icon,
		);
	}

	public function testSchemaResolvesTitleFromPropertyAttribute(): void
	{
		$registry = Registry::withDefaults();
		$schema = new Schema(NodeWithPropertyTitleAttribute::class, $registry);

		$this->assertEquals('heading', $schema->titleField);
	}

	public function testSchemaThrowsForPropertyTitleOnNonFieldProperty(): void
	{
		$registry = Registry::withDefaults();

		$this->throws(
			RuntimeException::class,
			"The #[Title] attribute on property 'Celemas\\Cms\\Tests\\Fixtures\\Node\\NodeWithInvalidPropertyTitleAttribute::heading' requires a field-typed property.",
		);
		new Schema(NodeWithInvalidPropertyTitleAttribute::class, $registry);
	}

	public function testSchemaDeletableFalse(): void
	{
		$registry = Registry::withDefaults();
		$schema = new Schema(PlainBlock::class, $registry);

		$this->assertFalse($schema->deletable);
	}

	public function testSchemaWithoutRegistryUsesDefaults(): void
	{
		$schema = new Schema(NodeWithNameAttribute::class);

		// Without a registry, attributes are not processed by handlers.
		// All properties fall back to defaults derived from the class name.
		$this->assertEquals('NodeWithNameAttribute', $schema->label);
		$this->assertEquals('node-with-name-attribute', $schema->handle);
	}

	// -- Custom (third-party) attribute registration --------------------------

	public function testCustomAttributeAvailableViaSchemaGet(): void
	{
		$registry = Registry::withDefaults();
		$registry->register(CustomIcon::class, new CustomIconHandler());

		$schema = new Schema(NodeWithCustomAttribute::class, $registry);

		$this->assertEquals('star', $schema->get('icon'));
	}

	public function testCustomAttributeIncludedInProperties(): void
	{
		$registry = Registry::withDefaults();
		$registry->register(CustomIcon::class, new CustomIconHandler());

		$schema = new Schema(NodeWithCustomAttribute::class, $registry);
		$props = $schema->properties();

		$this->assertArrayHasKey('icon', $props);
		$this->assertEquals('star', $props['icon']);
	}

	public function testCustomAttributeAccessibleViaTypes(): void
	{
		$registry = Registry::withDefaults();
		$registry->register(CustomIcon::class, new CustomIconHandler());

		$types = new Types($registry);

		$this->assertEquals('star', $types->get(NodeWithCustomAttribute::class, 'icon'));
		$this->assertEquals('Custom Node', $types->get(NodeWithCustomAttribute::class, 'label'));
		$this->assertTrue((bool) $types->get(NodeWithCustomAttribute::class, 'routable', false));
	}

	public function testCustomAttributeDefaultsToNull(): void
	{
		$types = new Types();

		$this->assertNull($types->get(PlainPage::class, 'icon'));
		$this->assertNull($types->get(PlainPage::class, 'icon', 'fallback'));
	}

	public function testUnregisteredCustomAttributeIsSilentlyIgnored(): void
	{
		$registry = Registry::withDefaults();
		// Intentionally NOT registering CustomIconHandler

		$schema = new Schema(NodeWithCustomAttribute::class, $registry);

		// Built-in attributes still resolve correctly
		$this->assertEquals('Custom Node', $schema->label);
		$this->assertTrue($schema->routable);

		// Custom attribute is just missing
		$this->assertNull($schema->get('icon'));
	}

	// -- Schema::properties() -------------------------------------------------

	public function testSchemaPropertiesContainsAllBuiltinKeys(): void
	{
		$registry = Registry::withDefaults();
		$schema = new Schema(PlainPage::class, $registry);
		$props = $schema->properties();

		$this->assertArrayHasKey('label', $props);
		$this->assertArrayHasKey('handle', $props);
		$this->assertArrayHasKey('renderer', $props);
		$this->assertArrayHasKey('routable', $props);
		$this->assertArrayHasKey('renderable', $props);
		$this->assertArrayHasKey('route', $props);
		$this->assertArrayHasKey('permission', $props);
		$this->assertArrayHasKey('icon', $props);
		$this->assertArrayHasKey('titleField', $props);
		$this->assertArrayHasKey('fieldOrder', $props);
		$this->assertArrayHasKey('deletable', $props);
		$this->assertArrayHasKey('children', $props);
	}

	// -- Types with custom registry -------------------------------------------

	public function testTypesWithCustomRegistryResolvesAllAttributes(): void
	{
		$registry = Registry::withDefaults();
		$registry->register(CustomIcon::class, new CustomIconHandler());

		$types = new Types($registry);

		// Built-in resolution still works
		$this->assertEquals('plain-page', $types->get(PlainPage::class, 'handle'));
		$this->assertEquals('Plain Page', $types->get(PlainPage::class, 'label'));
		$this->assertTrue((bool) $types->get(PlainPage::class, 'routable', false));

		// Custom resolution
		$this->assertEquals('star', $types->get(NodeWithCustomAttribute::class, 'icon'));
	}

	public function testTypesCachesSchemaInstances(): void
	{
		$types = new Types();

		$schema1 = $types->schemaOf(PlainPage::class);
		$schema2 = $types->schemaOf(PlainPage::class);

		$this->assertSame($schema1, $schema2);
	}

	public function testTypesClearCacheRemovesCachedSchemas(): void
	{
		$types = new Types();

		$schema1 = $types->schemaOf(PlainPage::class);
		$types->clearCache();
		$schema2 = $types->schemaOf(PlainPage::class);

		$this->assertNotSame($schema1, $schema2);
		$this->assertEquals($schema1->handle, $schema2->handle);
	}

	// -- Derived properties ---------------------------------------------------

	public function testRenderableDefaultsToRoutable(): void
	{
		$registry = Registry::withDefaults();

		// NodeWithHandleAttribute has #[Handle] but no #[Render] or #[Route].
		// renderable defaults to routable, which is false => renderable = false
		$schema = new Schema(NodeWithHandleAttribute::class, $registry);
		$this->assertFalse($schema->renderable);
		$this->assertEquals('node-with-custom-handle-attribute', $schema->renderer);
	}

	public function testRoutableNodeIsRenderableWithoutRenderAttribute(): void
	{
		$registry = Registry::withDefaults();

		// NodeWithRouteAttribute has #[Route] but no #[Render].
		// renderable defaults to routable => true
		$schema = new Schema(NodeWithRouteAttribute::class, $registry);
		$this->assertTrue($schema->routable);
		$this->assertTrue($schema->renderable);
	}
}

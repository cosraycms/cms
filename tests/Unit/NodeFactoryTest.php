<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\Request;
use Cosray\Column;
use Cosray\Context;
use Cosray\Exception\NoSuchProperty;
use Cosray\Locales;
use Cosray\Node\Factory;
use Cosray\Node\Node;
use Cosray\Node\Serializer;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Node\NodeWithClassTitleAttribute;
use Cosray\Tests\Fixtures\Node\NodeWithInjectedType;
use Cosray\Tests\Fixtures\Node\NodeWithNumericTitleField;
use Cosray\Tests\Fixtures\Node\NodeWithPropertyTitleAttribute;
use Cosray\Tests\Fixtures\Node\NodeWithTitleMethodWithoutInterface;
use Cosray\Tests\Fixtures\Node\PlainBlock;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\Fixtures\Node\PlainPageWithInit;
use Cosray\Tests\Fixtures\Node\TestPage;
use Cosray\Tests\TestCase;
use Cosray\Uid;
use stdClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeFactoryTest extends TestCase
{
	private Context $context;
	private \Cosray\Cms $cms;
	private Factory $factory;
	private Types $types;
	private Uid $uid;

	protected function setUp(): void
	{
		parent::setUp();
		$this->types = new Types();
		$this->uid = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13);

		$this->context = $this->createContext();
		$this->cms = $this->createStub(\Cosray\Cms::class);
		$this->factory = new Factory($this->container(), types: $this->types, uid: $this->uid);
	}

	private function createContext(): Context
	{
		$psrRequest = $this->psrRequest();
		$locales = new Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', domains: ['www.example.de'], fallback: 'en');

		$psrRequest = $psrRequest
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());

		$request = new Request($psrRequest);

		return new Context(
			$this->db(),
			$request,
			$this->config(['path.prefix' => '/cms']),
			$this->container(),
			$this->factory(),
		);
	}

	// -- Plain object creation ------------------------------------------------

	public function testCreatePlainPage(): void
	{
		$data = [
			'uid' => 'plain-page-1',
			'content' => [
				'heading' => ['value' => ['en' => 'Hello World']],
				'body' => ['value' => ['en' => 'Some body text']],
			],
		];

		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, $data);

		$this->assertInstanceOf(PlainPage::class, $node);
	}

	public function testCreatePlainBlock(): void
	{
		$node = $this->factory->create(PlainBlock::class, $this->context, $this->cms, [
			'uid' => 'plain-block-1',
			'content' => [],
		]);

		$this->assertInstanceOf(PlainBlock::class, $node);
	}

	public function testCreateTestPage(): void
	{
		$node = $this->factory->create(TestPage::class, $this->context, $this->cms, [
			'uid' => 'test-page-1',
			'content' => [],
		]);

		$this->assertInstanceOf(TestPage::class, $node);
	}

	public function testCreateNodeWithInjectedType(): void
	{
		$node = $this->factory->create(NodeWithInjectedType::class, $this->context, $this->cms, [
			'uid' => 'typed-node-1',
			'content' => [],
		]);

		$this->assertInstanceOf(NodeWithInjectedType::class, $node);
		$this->assertSame('Type Injected Node', $node->title());
		$this->assertSame('node-with-injected-type', $node->typeHandle());
	}

	// -- Field hydration ------------------------------------------------------

	public function testPlainPageFieldsAreHydrated(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'hydrated-1',
			'content' => [
				'heading' => ['value' => ['en' => 'Test Heading']],
			],
		]);

		$fieldNames = Factory::fieldNamesFor($node);
		$this->assertContains('heading', $fieldNames);
		$this->assertContains('body', $fieldNames);
		$this->assertCount(2, $fieldNames);
	}

	public function testPlainPageTitleResolution(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'titled-1',
			'content' => [
				'heading' => ['value' => ['en' => 'My Title']],
			],
		]);

		$serializer = new Serializer($this->types, $this->uid);
		$title = $serializer->resolveTitle($node);
		$this->assertEquals('My Title', $title);
	}

	public function testPlainPageTitleReturnsEmptyWhenNoContent(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'untitled-1',
			'content' => [],
		]);

		$serializer = new Serializer($this->types, $this->uid);
		$title = $serializer->resolveTitle($node);
		$this->assertSame('', $title);
	}

	public function testPropertyTitleAttributeResolvesNodeTitleFromField(): void
	{
		$node = $this->factory->create(
			NodeWithPropertyTitleAttribute::class,
			$this->context,
			$this->cms,
			[
				'uid' => 'property-title-1',
				'content' => [
					'heading' => ['value' => 'Property Title'],
				],
			],
		);

		$serializer = new Serializer($this->types, $this->uid);
		$title = $serializer->resolveTitle($node);
		$this->assertSame('Property Title', $title);
	}

	public function testNodeTitleResolvesFromClassTitleAttribute(): void
	{
		$node = $this->factory->create(NodeWithClassTitleAttribute::class, $this->context, $this->cms, [
			'uid' => 'node-title-class-1',
			'content' => [
				'heading' => ['value' => 'Node Class Title'],
			],
		]);

		$proxy = $this->factory->proxy($node, $this->context->request);

		$this->assertSame('Node Class Title', $proxy->title());
	}

	public function testNodeTitleResolvesFromPropertyTitleAttribute(): void
	{
		$node = $this->factory->create(
			NodeWithPropertyTitleAttribute::class,
			$this->context,
			$this->cms,
			[
				'uid' => 'node-title-property-1',
				'content' => [
					'heading' => ['value' => 'Node Property Title'],
				],
			],
		);

		$proxy = $this->factory->proxy($node, $this->context->request);

		$this->assertSame('Node Property Title', $proxy->title());
	}

	public function testNodeTitleIgnoresMethodWithoutTitleInterface(): void
	{
		$node = $this->factory->create(
			NodeWithTitleMethodWithoutInterface::class,
			$this->context,
			$this->cms,
			[
				'uid' => 'node-title-no-interface-1',
				'content' => [
					'title' => ['value' => 'Property fallback title'],
				],
			],
		);

		$proxy = $this->factory->proxy($node, $this->context->request);

		$this->assertSame('Property fallback title', $proxy->title());
	}

	public function testNodeTitleFieldMustBeText(): void
	{
		$node = $this->factory->create(NodeWithNumericTitleField::class, $this->context, $this->cms, [
			'uid' => 'node-title-number-1',
			'content' => [
				'count' => ['value' => 42],
			],
		]);

		$proxy = $this->factory->proxy($node, $this->context->request);

		$this->assertSame('', $proxy->title());
	}

	public function testColumnTitleResolvesFromClassTitleAttribute(): void
	{
		$node = $this->factory->create(NodeWithClassTitleAttribute::class, $this->context, $this->cms, [
			'uid' => 'column-title-class-1',
			'content' => [
				'heading' => ['value' => 'Class Attribute Title'],
			],
		]);

		$column = Column::new('Title', 'title');
		$row = $column->get($this->factory->proxy($node, $this->context->request));

		$this->assertSame('Class Attribute Title', $row['value']);
	}

	public function testColumnTitleResolvesFromPropertyTitleAttribute(): void
	{
		$node = $this->factory->create(
			NodeWithPropertyTitleAttribute::class,
			$this->context,
			$this->cms,
			[
				'uid' => 'column-title-property-1',
				'content' => [
					'heading' => ['value' => 'Property Attribute Title'],
				],
			],
		);

		$column = Column::new('Title', 'title');
		$row = $column->get($this->factory->proxy($node, $this->context->request));

		$this->assertSame('Property Attribute Title', $row['value']);
	}

	// -- WeakMap data storage -------------------------------------------------

	public function testDataForReturnsRawData(): void
	{
		$data = [
			'uid' => 'data-1',
			'handle' => 'plain-handle',
			'type_handle' => 'plain-page',
			'content' => ['heading' => ['value' => ['en' => 'Hello']]],
		];

		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, $data);
		$stored = Factory::dataFor($node);

		$this->assertEquals('data-1', $stored['uid']);
		$this->assertEquals('plain-handle', $stored['handle']);
	}

	public function testFieldNamesForReturnsFieldNames(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'fields-1',
			'content' => [],
		]);

		$fieldNames = Factory::fieldNamesFor($node);
		$this->assertEquals(['heading', 'body'], $fieldNames);
	}

	public function testMetaReturnsDataValue(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'meta-1',
			'handle' => 'plain-handle',
			'type_handle' => 'plain-page',
			'published' => true,
			'content' => [],
		]);

		$this->assertEquals('meta-1', Factory::meta($node, 'uid'));
		$this->assertEquals('plain-handle', Factory::meta($node, 'handle'));
		$this->assertTrue(Factory::meta($node, 'published'));
		$this->assertNull(Factory::meta($node, 'nonexistent'));
	}

	public function testDataForUnknownNodeReturnsEmpty(): void
	{
		$unknown = new stdClass();
		$this->assertEmpty(Factory::dataFor($unknown));
		$this->assertEmpty(Factory::fieldNamesFor($unknown));
	}

	// -- HasInit callback -----------------------------------------------------

	public function testHasInitIsCalledForPlainObject(): void
	{
		$node = $this->factory->create(PlainPageWithInit::class, $this->context, $this->cms, [
			'uid' => 'init-1',
			'content' => [],
		]);

		$this->assertInstanceOf(PlainPageWithInit::class, $node);
		$this->assertTrue($node->initialized);
	}

	// -- Blueprint creation ---------------------------------------------------

	public function testBlueprintCreatesEmptyPlainNode(): void
	{
		$node = $this->factory->blueprint(PlainPage::class, $this->context, $this->cms);

		$this->assertInstanceOf(PlainPage::class, $node);
		$fieldNames = Factory::fieldNamesFor($node);
		$this->assertCount(2, $fieldNames);
	}

	// -- NodeMeta with plain objects ------------------------------------------

	public function testNodeMetaRoutableForPlainPage(): void
	{
		$this->assertTrue((bool) $this->types->get(PlainPage::class, 'routable', false));
	}

	public function testNodeMetaNotRenderableForPlainBlock(): void
	{
		$this->assertFalse((bool) $this->types->get(PlainBlock::class, 'renderable', false));
	}

	public function testNodeMetaIsNodeForPlainPage(): void
	{
		$this->assertTrue($this->types->isNode(PlainPage::class));
	}

	public function testNodeMetaHandleForPlainPage(): void
	{
		$this->assertEquals('plain-page', $this->types->get(PlainPage::class, 'handle'));
	}

	public function testNodeMetaLabelForPlainPage(): void
	{
		$this->assertEquals('Plain Page', $this->types->get(PlainPage::class, 'label'));
	}

	public function testNodeMetaTitleFieldForPlainPage(): void
	{
		$this->assertEquals('heading', $this->types->get(PlainPage::class, 'titleField'));
	}

	public function testNodeMetaFieldOrderForPlainPage(): void
	{
		$this->assertEquals(['heading', 'body'], $this->types->get(PlainPage::class, 'fieldOrder'));
	}

	public function testNodeMetaDeletableForPlainBlock(): void
	{
		$this->assertFalse((bool) $this->types->get(PlainBlock::class, 'deletable', true));
	}

	public function testNodeMetaDeletableDefaultsToTrue(): void
	{
		$this->assertTrue((bool) $this->types->get(PlainPage::class, 'deletable', true));
	}

	// -- Serializer with plain objects ------------------------------------

	public function testSerializerFieldsForPlainPage(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'ser-1',
			'content' => [],
		]);

		$serializer = new Serializer($this->types, $this->uid);
		$fieldNames = Factory::fieldNamesFor($node);
		$fields = $serializer->fields($node, $fieldNames);

		$this->assertCount(2, $fields);
		$this->assertEquals('heading', $fields[0]['name']);
		$this->assertEquals('body', $fields[1]['name']);
	}

	public function testSerializerBlueprintForPlainPage(): void
	{
		$node = $this->factory->blueprint(PlainPage::class, $this->context, $this->cms);
		$fieldNames = Factory::fieldNamesFor($node);
		$locales = $this->context->locales();

		$serializer = new Serializer($this->types, $this->uid);
		$blueprint = $serializer->blueprint($node, $fieldNames, $locales);

		$this->assertArrayHasKey('uid', $blueprint);
		$this->assertArrayHasKey('content', $blueprint);
		$this->assertArrayHasKey('handle', $blueprint);
		$this->assertArrayHasKey('fields', $blueprint);
		$this->assertArrayHasKey('type', $blueprint);
		$this->assertTrue($blueprint['type']['routable']);
		$this->assertTrue($blueprint['type']['renderable']);
		$this->assertNull($blueprint['handle']);
		$this->assertEquals('plain-page', $blueprint['type']['handle']);
		$this->assertEquals(PlainPage::class, $blueprint['type']['class']);
		$this->assertArrayHasKey('route', $blueprint);
		$this->assertFalse($blueprint['published']);
	}

	// -- Node wrapper with plain objects --------------------------------------

	public function testNodeFieldAccess(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'proxy-1',
			'content' => [
				'heading' => ['value' => ['en' => 'Proxy Title']],
			],
		]);

		$fieldNames = Factory::fieldNamesFor($node);
		$proxy = new Node($node, $fieldNames, $this->types);

		$this->assertTrue(isset($proxy->heading));
		$this->assertEquals('Proxy Title', (string) $proxy->heading);
	}

	public function testNodeMethodDelegation(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'proxy-2',
			'content' => [
				'heading' => ['value' => ['en' => 'Method Test']],
			],
		]);

		$fieldNames = Factory::fieldNamesFor($node);
		$proxy = new Node($node, $fieldNames, $this->types);

		$this->assertEquals('Method Test', $proxy->title());
	}

	public function testNodeMetaPropertyProvidesNodeData(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'proxy-meta-1',
			'published' => true,
			'content' => [],
		]);

		$fieldNames = Factory::fieldNamesFor($node);
		$proxy = new Node($node, $fieldNames, $this->types);

		$this->assertEquals('proxy-meta-1', $proxy->meta->uid);
		$this->assertTrue($proxy->meta->published);
		$this->assertEquals('Plain Page', $proxy->meta->name);
	}

	public function testNodeMetaMethodCompatibility(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'proxy-meta-2',
			'published' => true,
			'content' => [],
		]);

		$fieldNames = Factory::fieldNamesFor($node);
		$proxy = new Node($node, $fieldNames, $this->types);

		$this->assertSame('proxy-meta-2', $proxy->meta('uid'));
		$this->assertSame('fallback', $proxy->meta('missing', 'fallback'));
	}

	public function testNodeMetaPropertyFailsFastForUnknownKey(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'proxy-meta-3',
			'published' => true,
			'content' => [],
		]);

		$fieldNames = Factory::fieldNamesFor($node);
		$proxy = new Node($node, $fieldNames, $this->types);

		$this->assertFalse(isset($proxy->meta->missing));
		$this->assertNull($proxy->meta->get('missing'));
		$this->assertSame('fallback', $proxy->meta->get('missing', 'fallback'));

		$this->throws(
			NoSuchProperty::class,
			"The node '" . PlainPage::class . "' doesn't have the meta property 'missing'",
		);
		$proxy->meta->missing;
	}

	public function testNodeUnsetFieldReturnsNull(): void
	{
		$node = $this->factory->create(PlainPage::class, $this->context, $this->cms, [
			'uid' => 'proxy-3',
			'content' => [],
		]);

		$fieldNames = Factory::fieldNamesFor($node);
		$proxy = new Node($node, $fieldNames, $this->types);

		$this->assertNull($proxy->heading);
		$this->assertFalse(isset($proxy->heading));
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Field\FieldHydrator;
use Cosray\Node\Factory;
use Cosray\Node\Serializer;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Node\TestDocument;
use Cosray\Tests\Fixtures\Node\TestMediaDocument;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Uid;

final class FieldPropertiesTest extends IntegrationTestCase
{
	private Factory $nodeFactory;
	private FieldHydrator $hydrator;

	protected function setUp(): void
	{
		parent::setUp();
		$this->nodeFactory = new Factory(
			$this->container(),
			types: new Types(),
			uid: new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
		);
		$this->hydrator = $this->nodeFactory->hydrator();
	}

	public function testFieldPropertiesIncludesNameAndType(): void
	{
		$context = $this->createContext();
		$finder = $this->createCms();

		$node = $this->nodeFactory->create(TestDocument::class, $context, $finder, ['content' => []]);

		$properties = $this->hydrator->getField($node, 'title')->properties();

		$this->assertArrayHasKey('name', $properties);
		$this->assertArrayHasKey('type', $properties);
		$this->assertEquals('title', $properties['name']);
		$this->assertEquals(\Cosray\Field\Text::class, $properties['type']);
	}

	public function testFieldPropertiesCollectsFromMultipleCapabilities(): void
	{
		$context = $this->createContext();
		$finder = $this->createCms();

		$node = $this->nodeFactory->create(TestDocument::class, $context, $finder, ['content' => []]);

		$properties = $this->hydrator->getField($node, 'title')->properties();

		// From Label capability
		$this->assertArrayHasKey('label', $properties);
		$this->assertEquals('Document Title', $properties['label']);

		// From Required capability
		$this->assertArrayHasKey('required', $properties);
		$this->assertTrue($properties['required']);

		// From Validate capability
		$this->assertArrayHasKey('validators', $properties);
		$this->assertContains('minLength:3', $properties['validators']);
		$this->assertContains('maxLength:100', $properties['validators']);
	}

	public function testFieldPropertiesHandlesHiddenAndImmutable(): void
	{
		$context = $this->createContext();
		$finder = $this->createCms();

		$node = $this->nodeFactory->create(TestDocument::class, $context, $finder, ['content' => []]);

		$properties = $this->hydrator->getField($node, 'internalId')->properties();

		$this->assertArrayHasKey('hidden', $properties);
		$this->assertTrue($properties['hidden']);

		$this->assertArrayHasKey('immutable', $properties);
		$this->assertTrue($properties['immutable']);
	}

	public function testFieldPropertiesHandlesResizableProperties(): void
	{
		$context = $this->createContext();
		$finder = $this->createCms();

		$node = $this->nodeFactory->create(TestDocument::class, $context, $finder, ['content' => []]);

		$properties = $this->hydrator->getField($node, 'intro')->properties();

		$this->assertArrayHasKey('rows', $properties);
		$this->assertEquals(5, $properties['rows']);

		$this->assertArrayHasKey('width', $properties);
		$this->assertEquals(12, $properties['width']);

		$this->assertArrayHasKey('translate', $properties);
		$this->assertTrue($properties['translate']);
		$this->assertSame('symmetric', $properties['translateMode']);

		$this->assertArrayHasKey('description', $properties);
		$this->assertEquals('A brief introduction to the document', $properties['description']);
	}

	public function testBlocksFieldPropertiesIncludesColumns(): void
	{
		$context = $this->createContext();
		$finder = $this->createCms();

		$node = $this->nodeFactory->create(
			TestMediaDocument::class,
			$context,
			$finder,
			['content' => []],
		);

		$properties = $this->hydrator->getField($node, 'contentBlocks')->properties();

		$this->assertArrayHasKey('columns', $properties);
		$this->assertEquals(12, $properties['columns']);

		$this->assertArrayHasKey('minCellWidth', $properties);
		$this->assertEquals(2, $properties['minCellWidth']);

		$this->assertArrayHasKey('translate', $properties);
		$this->assertTrue($properties['translate']);
		$this->assertSame('asymmetric', $properties['translateMode']);
	}

	public function testImageFieldPropertiesDoesNotIncludeLimitWithoutSchema(): void
	{
		$context = $this->createContext();
		$finder = $this->createCms();

		$node = $this->nodeFactory->create(
			TestMediaDocument::class,
			$context,
			$finder,
			['content' => []],
		);

		$properties = $this->hydrator->getField($node, 'gallery')->properties();

		$this->assertArrayNotHasKey('limit', $properties);

		$this->assertArrayHasKey('translate', $properties);
		$this->assertTrue($properties['translate']);
		$this->assertSame('asymmetric', $properties['translateMode']);
	}

	public function testOptionFieldPropertiesIncludesOptions(): void
	{
		$context = $this->createContext();
		$finder = $this->createCms();

		$node = $this->nodeFactory->create(
			TestMediaDocument::class,
			$context,
			$finder,
			['content' => []],
		);

		$properties = $this->hydrator->getField($node, 'category')->properties();

		$this->assertArrayHasKey('options', $properties);
		$this->assertEquals(['news', 'blog', 'tutorial'], $properties['options']);
	}

	public function testNodeFieldsMethodReturnsAllFieldProperties(): void
	{
		$context = $this->createContext();
		$finder = $this->createCms();

		$node = $this->nodeFactory->create(TestDocument::class, $context, $finder, ['content' => []]);

		$fieldNames = Factory::fieldNamesFor($node);
		$serializer = new Serializer(new Types(), $this->nodeFactory->uid());
		$fields = $serializer->fields($node, $fieldNames);

		$this->assertIsArray($fields);
		$this->assertCount(3, $fields); // title, intro, internalId

		// Check that each field has the basic properties
		foreach ($fields as $field) {
			$this->assertArrayHasKey('name', $field);
			$this->assertArrayHasKey('type', $field);
		}

		// Find title field and verify its properties
		$titleField = array_values(array_filter($fields, static fn($f) => $f['name'] === 'title'))[0];
		$this->assertEquals('Document Title', $titleField['label']);
		$this->assertTrue($titleField['required']);
	}
}

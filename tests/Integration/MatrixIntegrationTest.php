<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Node\Factory;
use Cosray\Node\FieldOwner;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Node\TestMatrix;
use Cosray\Tests\Fixtures\Node\TestNodeWithMatrix;
use Cosray\Tests\TestCase;

class MatrixIntegrationTest extends TestCase
{
	private function createContext(): \Cosray\Context
	{
		$psrRequest = $this->psrRequest();
		$locales = new \Cosray\Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', domains: ['www.example.de'], fallback: 'en');

		$psrRequest = $psrRequest
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());

		$request = new \Celemas\Core\Request($psrRequest);

		return new \Cosray\Context(
			$this->db(),
			$request,
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	public function testMyMatrixIntegration(): void
	{
		$context = $this->createContext();
		$cms = $this->createStub(\Cosray\Cms::class);
		$nodeFactory = new Factory($this->container(), types: new Types());
		$hydrator = $nodeFactory->hydrator();

		$node = $nodeFactory->create(TestNodeWithMatrix::class, $context, $cms, [
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Test Node']],
				'matrix' => [
					'type' => 'matrix',
					'value' => [
						[
							'title' => ['type' => 'text', 'value' => ['en' => 'First Item']],
							'content' => ['type' => 'blocks', 'columns' => 12, 'value' => ['en' => []]],
						],
						[
							'title' => ['type' => 'text', 'value' => ['en' => 'Second Item']],
							'content' => ['type' => 'blocks', 'columns' => 12, 'value' => ['en' => []]],
						],
					],
				],
			],
		]);

		// Test that matrix field exists and is accessible
		$matrixField = $hydrator->getField($node, 'matrix');
		$this->assertInstanceOf(\Cosray\Field\Matrix::class, $matrixField);
		$matrixValue = $matrixField->value();
		$this->assertInstanceOf(\Cosray\Value\MatrixValue::class, $matrixValue);

		// Test matrix iteration
		$items = [];
		foreach ($matrixValue as $item) {
			$items[] = $item;
		}

		$this->assertCount(2, $items);
		$this->assertInstanceOf(\Cosray\Value\MatrixItem::class, $items[0]);
		$this->assertInstanceOf(\Cosray\Value\MatrixItem::class, $items[1]);

		// Test subfield access
		$firstItem = $matrixValue->first();
		$this->assertNotNull($firstItem);
		$this->assertEquals('First Item', $firstItem->title->unwrap());
		$this->assertInstanceOf(\Cosray\Value\Blocks::class, $firstItem->content);

		// Test matrix methods
		$this->assertEquals(2, $matrixValue->count());
		$this->assertEquals('First Item', $matrixValue->first()->title->unwrap());
		$this->assertEquals('Second Item', $matrixValue->last()->title->unwrap());
	}

	public function testMyMatrixStructure(): void
	{
		$context = $this->createContext();
		$owner = new FieldOwner($context, 'test-node');

		$matrix = new TestMatrix(
			'test_matrix',
			$owner,
			new \Cosray\Value\ValueContext('test_matrix', []),
		);

		// Call value() to initialize subfields
		$matrixValue = $matrix->value();

		$structure = $matrix->structure();
		$this->assertEquals('matrix', $structure['type']);
		$this->assertIsArray($structure['value']);

		$subfields = $matrix->getSubfields();
		$this->assertArrayHasKey('title', $subfields);
		$this->assertArrayHasKey('content', $subfields);
		$this->assertInstanceOf(\Cosray\Field\Text::class, $subfields['title']);
		$this->assertInstanceOf(\Cosray\Field\Blocks::class, $subfields['content']);
	}

	public function testMatrixSubfieldTranslateStructure(): void
	{
		$context = $this->createContext();
		$owner = new FieldOwner($context, 'test-node');

		// Create matrix with one empty item
		$matrix = new TestMatrix(
			'test_matrix',
			$owner,
			new \Cosray\Value\ValueContext('test_matrix', [
				'type' => 'matrix',
				'value' => [
					[
						'title' => ['type' => 'text', 'value' => ''],
						'content' => ['type' => 'blocks', 'columns' => 12, 'value' => []],
					],
				],
			]),
		);

		$structure = $matrix->structure();

		// Subfields with #[Translate] should have locale keys in their value
		$this->assertCount(1, $structure['value']);
		$titleValue = $structure['value'][0]['title']['value'];

		// Should have locale structure, not empty string
		$this->assertIsArray(
			$titleValue,
			'Translatable subfield should have array value with locale keys',
		);
		$this->assertArrayHasKey('en', $titleValue);
		$this->assertArrayHasKey('de', $titleValue);
	}
}

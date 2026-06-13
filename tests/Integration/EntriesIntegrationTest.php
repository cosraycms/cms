<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Node\Factory;
use Cosray\Node\FieldOwner;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Node\TestEntries;
use Cosray\Tests\Fixtures\Node\TestNodeWithEntries;
use Cosray\Tests\TestCase;

class EntriesIntegrationTest extends TestCase
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

	public function testEntriesIntegration(): void
	{
		$context = $this->createContext();
		$cms = $this->createStub(\Cosray\Cms::class);
		$nodeFactory = new Factory($this->container(), types: new Types());
		$hydrator = $nodeFactory->hydrator();

		$node = $nodeFactory->create(TestNodeWithEntries::class, $context, $cms, [
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Test Node']],
				'entries' => [
					'type' => 'entries',
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

		$entriesField = $hydrator->getField($node, 'entries');
		$this->assertInstanceOf(\Cosray\Field\Entries::class, $entriesField);
		$entriesValue = $entriesField->value();
		$this->assertInstanceOf(\Cosray\Value\Entries::class, $entriesValue);

		$items = [];
		foreach ($entriesValue as $item) {
			$items[] = $item;
		}

		$this->assertCount(2, $items);
		$this->assertInstanceOf(\Cosray\Value\Entry::class, $items[0]);
		$this->assertInstanceOf(\Cosray\Value\Entry::class, $items[1]);

		$firstItem = $entriesValue->first();
		$this->assertNotNull($firstItem);
		$this->assertEquals('First Item', $firstItem->title->unwrap());
		$this->assertInstanceOf(\Cosray\Value\Blocks::class, $firstItem->content);

		$this->assertEquals(2, $entriesValue->count());
		$this->assertEquals('First Item', $entriesValue->first()->title->unwrap());
		$this->assertEquals('Second Item', $entriesValue->last()->title->unwrap());
	}

	public function testEntriesStructure(): void
	{
		$context = $this->createContext();
		$owner = new FieldOwner($context, 'test-node');

		$entries = new TestEntries(
			'test_entries',
			$owner,
			new \Cosray\Value\ValueContext('test_entries', []),
		);

		$entries->value();

		$structure = $entries->structure();
		$this->assertEquals('entries', $structure['type']);
		$this->assertIsArray($structure['value']);

		$entryFields = $entries->entryFields();
		$this->assertArrayHasKey('title', $entryFields);
		$this->assertArrayHasKey('content', $entryFields);
		$this->assertInstanceOf(\Cosray\Field\Text::class, $entryFields['title']);
		$this->assertInstanceOf(\Cosray\Field\Blocks::class, $entryFields['content']);
	}

	public function testEntryFieldTranslateStructure(): void
	{
		$context = $this->createContext();
		$owner = new FieldOwner($context, 'test-node');

		$entries = new TestEntries(
			'test_entries',
			$owner,
			new \Cosray\Value\ValueContext('test_entries', [
				'type' => 'entries',
				'value' => [
					[
						'title' => ['type' => 'text', 'value' => ''],
						'content' => ['type' => 'blocks', 'columns' => 12, 'value' => []],
					],
				],
			]),
		);

		$structure = $entries->structure();

		$this->assertCount(1, $structure['value']);
		$titleValue = $structure['value'][0]['title']['value'];

		$this->assertIsArray(
			$titleValue,
			'Translatable entry field should have array value with locale keys',
		);
		$this->assertArrayHasKey('en', $titleValue);
		$this->assertArrayHasKey('de', $titleValue);
	}
}

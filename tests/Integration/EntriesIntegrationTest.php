<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Field\Entries;
use Cosray\Field\Services;
use Cosray\Node\Factory;
use Cosray\Node\FieldOwner;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Node\TestAlternateEntry;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\Fixtures\Node\TestNodeWithEntries;
use Cosray\Tests\TestCase;
use Cosray\Uid;
use Cosray\Value\ValueContext;

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
		$nodeFactory = new Factory(
			$this->container(),
			Services::withDefaults(),
			new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
		);
		$hydrator = $nodeFactory->hydrator();

		$node = $nodeFactory->create(TestNodeWithEntries::class, $context, $cms, [
			'content' => [
				'title' => ['type' => \Cosray\Field\Text::class, 'value' => ['en' => 'Test Node']],
				'entries' => [
					'type' => Entries::class,
					'value' => [
						\Cosray\Field\Field::NEUTRAL_LOCALE => [
							[
								'uid' => 'entry1',
								'type' => TestEntry::class,
								'fields' => [
									'title' => ['type' => \Cosray\Field\Text::class, 'value' => ['en' => 'First Item']],
									'content' => [
										'type' => \Cosray\Field\Blocks::class,
										'meta' => ['columns' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 12]],
										'value' => ['en' => []],
									],
								],
							],
							[
								'uid' => 'entry2',
								'type' => TestEntry::class,
								'fields' => [
									'title' => ['type' => \Cosray\Field\Text::class, 'value' => ['en' => 'Second Item']],
									'content' => [
										'type' => \Cosray\Field\Blocks::class,
										'meta' => ['columns' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 12]],
										'value' => ['en' => []],
									],
								],
							],
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
		$this->assertSame(TestEntry::class, $firstItem->type);
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
		$entries = new Entries(
			'test_entries',
			$owner,
			new ValueContext('test_entries', []),
		);
		$entries->init(Services::withDefaults());
		$entries->allow(TestEntry::class, TestAlternateEntry::class);

		$entries->value();

		$structure = $entries->structure();
		$this->assertEquals(Entries::class, $structure['type']);
		$this->assertIsArray($structure['value']);

		$entryFields = $entries->entryFields(TestEntry::class);
		$this->assertArrayHasKey('title', $entryFields);
		$this->assertArrayHasKey('content', $entryFields);
		$this->assertInstanceOf(\Cosray\Field\Text::class, $entryFields['title']);
		$this->assertInstanceOf(\Cosray\Field\Blocks::class, $entryFields['content']);
	}

	public function testEntryFieldTranslateStructure(): void
	{
		$context = $this->createContext();
		$owner = new FieldOwner($context, 'test-node');
		$entries = new Entries(
			'test_entries',
			$owner,
			new ValueContext('test_entries', [
				'type' => Entries::class,
				'value' => [
					\Cosray\Field\Field::NEUTRAL_LOCALE => [
						[
							'uid' => 'entry1',
							'type' => TestEntry::class,
							'fields' => [
								'title' => ['type' => \Cosray\Field\Text::class, 'value' => ['en' => '']],
								'content' => [
									'type' => \Cosray\Field\Blocks::class,
									'meta' => ['columns' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 12]],
									'value' => ['en' => []],
								],
							],
						],
					],
				],
			]),
		);
		$entries->init(Services::withDefaults());
		$entries->allow(TestEntry::class);

		$structure = $entries->structure();

		$this->assertCount(1, $structure['value'][\Cosray\Field\Field::NEUTRAL_LOCALE]);
		$titleValue =
			$structure['value'][\Cosray\Field\Field::NEUTRAL_LOCALE][0]['fields']['title']['value'];

		$this->assertIsArray(
			$titleValue,
			'Translatable entry field should have array value with locale keys',
		);
		$this->assertArrayHasKey('en', $titleValue);
		$this->assertArrayHasKey('de', $titleValue);
	}
}

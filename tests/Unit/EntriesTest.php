<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Entries;
use Cosray\Field\FieldHydrator;
use Cosray\Field\Schema\Registry;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Node\FieldOwner;
use Cosray\Node\Types;
use Cosray\Schema\Allows;
use Cosray\Tests\Fixtures\Node\TestAlternateEntry;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\TestCase;
use Cosray\Value\Entries as EntriesValue;
use Cosray\Value\ValueContext;

class EntriesTest extends TestCase
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

		$request = new \Celema\Core\Request($psrRequest);

		return new \Cosray\Context(
			$this->db(),
			$request,
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	private function createEntries(array $data = []): Entries
	{
		$context = $this->createContext();
		$owner = new FieldOwner($context, 'test-node');
		$entries = new Entries(
			'test_entries',
			$owner,
			new ValueContext('test_entries', $data),
		);
		$entries->init(Services::withDefaults());
		$entries->allow(TestEntry::class, TestAlternateEntry::class);

		return $entries;
	}

	public function testEntriesFieldCreation(): void
	{
		$entries = $this->createEntries();

		$this->assertInstanceOf(Entries::class, $entries);
		$this->assertInstanceOf(EntriesValue::class, $entries->value());
		$this->assertSame([TestEntry::class, TestAlternateEntry::class], $entries->allowedEntryTypes());
		$this->assertArrayHasKey('title', $entries->entryFields(TestEntry::class));
		$this->assertArrayHasKey('content', $entries->entryFields(TestEntry::class));
	}

	public function testEntriesPropertiesExposeEntryTypes(): void
	{
		$properties = $this->createEntries()->properties();

		$this->assertSame(Entries::class, $properties['type']);
		$this->assertSame(TestEntry::class, $properties['entryTypes'][0]['type']);
		$this->assertSame('Test Entry', $properties['entryTypes'][0]['label']);
		$this->assertSame('title', $properties['entryTypes'][0]['fields'][0]['name']);
		$this->assertSame(TestAlternateEntry::class, $properties['entryTypes'][1]['type']);

		// Initial entry content comes from each field's structure().
		$init = $properties['entryTypes'][0]['init'];
		$this->assertSame(Text::class, $init['title']['type']);
		$this->assertArrayHasKey('value', $init['title']);
	}

	public function testEntriesStructureUsesEntryTypeEnvelope(): void
	{
		$structure = $this->createEntries()->structure([
			[
				'uid' => 'entry1',
				'type' => TestEntry::class,
				'fields' => [
					'title' => ['type' => Text::class, 'value' => ['en' => '']],
					'content' => ['type' => \Cosray\Field\Blocks::class, 'value' => ['en' => []]],
				],
			],
		]);

		$this->assertEquals(Entries::class, $structure['type']);
		$this->assertSame(
			TestEntry::class,
			$structure['value'][\Cosray\Field\Field::NEUTRAL_LOCALE][0]['type'],
		);
		$this->assertArrayHasKey(
			'title',
			$structure['value'][\Cosray\Field\Field::NEUTRAL_LOCALE][0]['fields'],
		);
		$this->assertArrayHasKey(
			'content',
			$structure['value'][\Cosray\Field\Field::NEUTRAL_LOCALE][0]['fields'],
		);
	}

	public function testEntriesShapeAcceptsAllowedEntryTypes(): void
	{
		$result = $this
			->createEntries()
			->shape()
			->validate([
				'type' => Entries::class,
				'value' => [
					\Cosray\Field\Field::NEUTRAL_LOCALE => [
						[
							'uid' => 'entry1',
							'type' => TestEntry::class,
							'fields' => [
								'title' => ['type' => Text::class, 'value' => ['en' => 'Title']],
								'content' => [
									'type' => \Cosray\Field\Blocks::class,
									'value' => ['en' => []],
									'meta' => ['columns' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 12]],
								],
							],
						],
						[
							'uid' => 'entry2',
							'type' => TestAlternateEntry::class,
							'fields' => [
								'name' => [
									'type' => Text::class,
									'value' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 'Other'],
								],
							],
						],
					],
				],
			]);

		$this->assertTrue($result->valid());
		$this->assertSame(
			TestEntry::class,
			$result->values()['value'][\Cosray\Field\Field::NEUTRAL_LOCALE][0]['type'],
		);
		$this->assertSame(
			'Other',
			$result->values()['value'][\Cosray\Field\Field::NEUTRAL_LOCALE][1]['fields']['name']['value'][\Cosray\Field\Field::NEUTRAL_LOCALE],
		);
	}

	public function testEntriesShapeRejectsUnknownEntryTypes(): void
	{
		$result = $this
			->createEntries()
			->shape()
			->validate([
				'type' => Entries::class,
				'value' => [
					\Cosray\Field\Field::NEUTRAL_LOCALE => [
						['uid' => 'entry1', 'type' => self::class, 'fields' => []],
					],
				],
			]);

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has(['value', \Cosray\Field\Field::NEUTRAL_LOCALE, 0, 'type']));
	}

	public function testEntryFieldsHaveTranslateCapability(): void
	{
		$entries = $this->createEntries();
		$entryFields = $entries->entryFields(TestEntry::class);

		$titleField = $entryFields['title'];
		$this->assertTrue($titleField->isTranslatable(), 'Title entry field should be translatable');

		$properties = $entries->properties();
		$this->assertSame('symmetric', $properties['entryTypes'][0]['fields'][0]['translateMode']);
		$this->assertSame('asymmetric', $properties['entryTypes'][0]['fields'][1]['translateMode']);

		$structure = $entries->structure([
			[
				'uid' => 'entry1',
				'type' => TestEntry::class,
				'fields' => [
					'title' => ['type' => Text::class, 'value' => ['en' => '']],
					'content' => ['type' => \Cosray\Field\Blocks::class, 'value' => ['en' => []]],
				],
			],
		]);

		$titleValue =
			$structure['value'][\Cosray\Field\Field::NEUTRAL_LOCALE][0]['fields']['title']['value'];
		$this->assertIsArray($titleValue, 'Title value should be array with locale keys');
		$this->assertArrayHasKey('en', $titleValue);
		$this->assertArrayHasKey('de', $titleValue);
	}

	public function testAllowsRequiresEntriesField(): void
	{
		$context = $this->createContext();
		$owner = new FieldOwner($context, 'test-node');
		$node = new class {
			#[Allows(TestEntry::class)]
			protected Text $title;
		};

		$this->throws(RuntimeException::class);

		new FieldHydrator(new Services(Registry::withDefaults(), new Types()))->hydrate(
			$node,
			[],
			$owner,
		);
	}
}

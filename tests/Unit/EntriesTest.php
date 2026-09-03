<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Entries;
use Cosray\Field\FieldHydrator;
use Cosray\Field\Image;
use Cosray\Field\RichText;
use Cosray\Field\Schema\Registry;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Node\FieldOwner;
use Cosray\Node\Types;
use Cosray\Richtext\Envelope;
use Cosray\Schema\Allows;
use Cosray\Tests\Fixtures\Node\TestAlternateEntry;
use Cosray\Tests\Fixtures\Node\TestBlocksEntry;
use Cosray\Tests\Fixtures\Node\TestEmbeddedEntry;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\Fixtures\Node\TestNestedEntriesEntry;
use Cosray\Tests\Fixtures\Node\TestRichTextEntry;
use Cosray\Tests\Fixtures\Node\TestSplitFieldsetEntry;
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

	public function testEntriesControlCarriesEntryTypes(): void
	{
		$properties = $this->createEntries()->properties();
		$entryTypes = $properties['control']['props']['entryTypes'];

		$this->assertSame(Entries::class, $properties['type']);
		$this->assertSame('entries', $properties['control']['name']);
		$this->assertSame(TestEntry::class, $entryTypes[0]['type']);
		$this->assertSame('Test Entry', $entryTypes[0]['label']);
		$this->assertSame('title', $entryTypes[0]['fields'][0]['name']);
		$this->assertSame(TestAlternateEntry::class, $entryTypes[1]['type']);

		// Rich sub-fields arrive resolved to their element form.
		$this->assertSame('element', $entryTypes[0]['fields'][1]['control']['name']);
	}

	public function testEntriesControlCarriesLimits(): void
	{
		$control = $this->createEntries()->limit(4, min: 1)->control()->array();

		$this->assertSame(1, $control['props']['min']);
		$this->assertSame(4, $control['props']['max']);
	}

	public function testEntriesRejectNestedEntriesFields(): void
	{
		$entries = $this->createEntries()->allow(TestNestedEntriesEntry::class);
		$this->throws(RuntimeException::class, 'cannot contain nested entries field');

		$entries->properties();
	}

	public function testEntriesRejectNestedBlocksFields(): void
	{
		$entries = $this->createEntries()->allow(TestBlocksEntry::class);
		$this->throws(RuntimeException::class, "cannot contain nested blocks field 'body' in entry type");

		$entries->properties();
	}

	public function testEntriesExposeFlattenedEmbeddedFieldsAndFieldsets(): void
	{
		$entries = $this->createEntries()->allow(TestEmbeddedEntry::class);
		$fields = $entries->entryFieldsFor(TestEmbeddedEntry::class);
		$properties = $entries->properties();
		$entryType = $properties['control']['props']['entryTypes'][2];

		$this->assertSame(['title', 'body'], array_keys($fields));
		$this->assertSame(['title', 'body'], array_column($entryType['fields'], 'name'));
		$this->assertSame(
			[
				[
					'name' => 'baseFields',
					'label' => 'Entry base fields',
					'description' => 'Reusable entry fields',
					'width' => 50,
					'fields' => ['title', 'body'],
				],
			],
			$entryType['fieldsets'],
		);
	}

	public function testEntryFieldOrderCannotSplitAFieldset(): void
	{
		$entries = $this->createEntries()->allow(TestSplitFieldsetEntry::class);
		$this->throws(RuntimeException::class, "splits fieldset 'baseFields'");

		$entries->properties();
	}

	public function testEntriesStructureUsesEntryTypeEnvelope(): void
	{
		$structure = $this->createEntries()->structure([
			[
				'uid' => 'entry1',
				'type' => TestEntry::class,
				'fields' => [
					'title' => ['type' => Text::class, 'value' => ['en' => '']],
					'content' => ['type' => Image::class, 'value' => ['en' => []]],
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

	public function testEntriesStructureNormalizesNestedRichTextEnvelope(): void
	{
		$document = [
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [['type' => 'text', 'text' => 'Schedule']],
				],
			],
		];
		$structure = $this
			->createEntries()
			->allow(TestRichTextEntry::class)
			->structure([
				[
					'uid' => 'entry1',
					'type' => TestRichTextEntry::class,
					'fields' => [
						'content' => [
							'type' => RichText::class,
							'value' => [RichText::NEUTRAL_LOCALE => $document],
							'format' => Envelope::FORMAT,
							'version' => Envelope::VERSION,
						],
					],
				],
			]);

		$this->assertSame(
			[
				'type' => RichText::class,
				'value' => [RichText::NEUTRAL_LOCALE => $document],
				'format' => Envelope::FORMAT,
				'version' => Envelope::VERSION,
			],
			$structure['value'][Entries::NEUTRAL_LOCALE][0]['fields']['content'],
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
								'content' => ['type' => Image::class, 'value' => ['en' => []]],
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
		$entryTypes = $properties['control']['props']['entryTypes'];
		$this->assertSame('symmetric', $entryTypes[0]['fields'][0]['translateMode']);
		$this->assertSame('asymmetric', $entryTypes[0]['fields'][1]['translateMode']);

		$structure = $entries->structure([
			[
				'uid' => 'entry1',
				'type' => TestEntry::class,
				'fields' => [
					'title' => ['type' => Text::class, 'value' => ['en' => '']],
					'content' => ['type' => Image::class, 'value' => ['en' => []]],
				],
			],
		]);

		$titleValue = $structure['value'][\Cosray\Field\Field::NEUTRAL_LOCALE][0]['fields']['title']['value'];
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

<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Context;
use Cosray\Exception\NoSuchProperty;
use Cosray\Field\Entries as EntriesField;
use Cosray\Field\Services;
use Cosray\Node\FieldOwner;
use Cosray\Tests\Fixtures\Node\TestAlternateEntry;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\TestCase;
use Cosray\Value\Entries;
use Cosray\Value\Entry;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class EntriesValueTest extends TestCase
{
	private function createContext(): Context
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

		return new Context(
			$this->db(),
			$request,
			$this->config(['path.prefix' => '/cms']),
			$this->container(),
			$this->factory(),
		);
	}

	private function createOwner(Context $context): FieldOwner
	{
		return new FieldOwner($context, 'test-node');
	}

	private function createEntriesValue(array $data): Entries
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new EntriesField('entries', $owner, new ValueContext('entries', $data));
		$field->init(Services::withDefaults());
		$field->allow(TestEntry::class, TestAlternateEntry::class);

		return $field->value();
	}

	private function entriesData(): array
	{
		return [
			'type' => EntriesField::class,
			'value' => [
				\Cosray\Field\Field::NEUTRAL_LOCALE => [
					[
						'uid' => 'entry1',
						'type' => TestEntry::class,
						'fields' => [
							'title' => [
								'type' => \Cosray\Field\Text::class,
								'value' => ['en' => 'First Item', 'de' => 'Erstes'],
							],
							'content' => [
								'type' => \Cosray\Field\Blocks::class,
								'meta' => ['columns' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 12]],
								'value' => ['en' => [], 'de' => []],
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
								'value' => ['en' => [], 'de' => []],
							],
						],
					],
				],
			],
		];
	}

	public function testEntriesValueAccessorsReturnEntriesAndValues(): void
	{
		$value = $this->createEntriesValue($this->entriesData());

		$this->assertSame(2, $value->count());
		$this->assertInstanceOf(Entry::class, $value->first());
		$this->assertInstanceOf(Entry::class, $value->last());
		$this->assertSame(TestEntry::class, $value->first()?->type);
		$this->assertSame('First Item', $value->first()?->title->unwrap());
		$this->assertSame('Second Item', $value->last()?->title->unwrap());
		$this->assertSame('Second Item', $value->get(1)?->title->unwrap());
		$this->assertNull($value->get(2));
		$this->assertSame(12, $value->first()?->content->columns());
	}

	public function testEntriesValueIssetIsFalseWhenEmpty(): void
	{
		$value = $this->createEntriesValue([
			'type' => EntriesField::class,
			'value' => [\Cosray\Field\Field::NEUTRAL_LOCALE => []],
		]);

		$this->assertFalse($value->isset());
		$this->assertSame(0, $value->count());
		$this->assertNull($value->first());
		$this->assertNull($value->last());
	}

	public function testEntriesValueJsonMatchesUnwrap(): void
	{
		$value = $this->createEntriesValue($this->entriesData());
		$unwrapped = $value->unwrap();

		$this->assertSame($unwrapped, $value->json());
		$this->assertCount(2, $unwrapped);
		$this->assertSame(TestEntry::class, $unwrapped[0]['type']);
		$this->assertArrayHasKey('title', $unwrapped[0]['fields']);
		$this->assertArrayHasKey('content', $unwrapped[0]['fields']);
	}

	public function testEntryThrowsOnUnknownField(): void
	{
		$value = $this->createEntriesValue($this->entriesData());
		$entry = $value->first();

		$this->assertInstanceOf(Entry::class, $entry);
		$this->throws(NoSuchProperty::class, "Entry doesn't have field 'missing'");

		$entry->missing;
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\Entries;
use Cosray\Node\FieldOwner;
use Cosray\Tests\Fixtures\Field\TestEntries;
use Cosray\Tests\TestCase;
use Cosray\Value\Entries as EntriesValue;

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

		$request = new \Celemas\Core\Request($psrRequest);

		return new \Cosray\Context(
			$this->db(),
			$request,
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	private function createEntries(array $data = []): TestEntries
	{
		$context = $this->createContext();
		$owner = new FieldOwner($context, 'test-node');

		return new TestEntries(
			'test_entries',
			$owner,
			new \Cosray\Value\ValueContext('test_entries', $data),
		);
	}

	public function testEntriesFieldCreation(): void
	{
		$entries = $this->createEntries();

		$this->assertInstanceOf(Entries::class, $entries);
		$this->assertInstanceOf(EntriesValue::class, $entries->value());
		$this->assertIsArray($entries->entryFields());
		$this->assertArrayHasKey('title', $entries->entryFields());
		$this->assertArrayHasKey('content', $entries->entryFields());
	}

	public function testEntriesStructure(): void
	{
		$structure = $this->createEntries()->structure();

		$this->assertEquals('entries', $structure['type']);
		$this->assertIsArray($structure['value']);
	}

	public function testEntriesShapeNormalizesLegacyMatrixType(): void
	{
		$result = $this
			->createEntries()
			->shape()
			->validate([
				'type' => 'matrix',
				'value' => [],
			]);

		$this->assertTrue($result->valid());
		$this->assertSame('entries', $result->values()['type']);
	}

	public function testEntryFieldsHaveTranslateCapability(): void
	{
		$entries = $this->createEntries();
		$entryFields = $entries->entryFields();

		$titleField = $entryFields['title'];
		$this->assertTrue($titleField->isTranslatable(), 'Title entry field should be translatable');

		$properties = $entries->properties();
		$this->assertSame('symmetric', $properties['entryFields'][0]['translateMode']);
		$this->assertSame('asymmetric', $properties['entryFields'][1]['translateMode']);

		$structure = $entries->structure([
			['title' => ['type' => 'text', 'value' => ''], 'content' => ['type' => 'blocks', 'value' => []]],
		]);

		$titleValue = $structure['value'][0]['title']['value'];
		$this->assertIsArray($titleValue, 'Title value should be array with locale keys');
		$this->assertArrayHasKey('en', $titleValue);
		$this->assertArrayHasKey('de', $titleValue);
	}

	public function testEntriesStructureFromLegacyMatrixValueContext(): void
	{
		$entries = $this->createEntries([
			'type' => 'matrix',
			'value' => [
				[
					'title' => ['type' => 'text', 'value' => ''],
					'content' => ['type' => 'blocks', 'value' => []],
				],
			],
		]);

		$structure = $entries->structure();

		$this->assertSame('entries', $structure['type']);
		$titleValue = $structure['value'][0]['title']['value'];
		$this->assertIsArray(
			$titleValue,
			'Title value should be array with locale keys, got: ' . var_export($titleValue, true),
		);
		$this->assertArrayHasKey('en', $titleValue);
		$this->assertArrayHasKey('de', $titleValue);
	}
}

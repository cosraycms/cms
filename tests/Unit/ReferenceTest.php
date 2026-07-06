<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\Request;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Field;
use Cosray\Field\Reference;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Locales;
use Cosray\Node\FieldOwner;
use Cosray\Tests\Fixtures\Node\TestArticle;
use Cosray\Tests\Fixtures\Node\TestNodeWithReference;
use Cosray\Tests\TestCase;
use Cosray\Value\Reference as ReferenceValue;
use Cosray\Value\ValueContext;
use ReflectionProperty;

class ReferenceTest extends TestCase
{
	private function createContext(): Context
	{
		$locales = new Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', domains: ['www.example.de'], fallback: 'en');

		$psrRequest = $this
			->psrRequest()
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());

		return new Context(
			$this->db(),
			new Request($psrRequest),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	private function createReference(string $name = 'related', array $data = []): Reference
	{
		$owner = new FieldOwner($this->createContext(), 'test-node');
		$field = new Reference($name, $owner, new ValueContext($name, $data));
		$field->init(Services::withDefaults());

		return $field;
	}

	public function testReferenceIsUnboundedMultiByDefault(): void
	{
		$field = $this->createReference();

		$this->assertInstanceOf(ReferenceValue::class, $field->value());
		$this->assertSame(-1, $field->getLimitMax());
		$this->assertSame('reference', $field->control()->name);
		$this->assertSame([], $field->targets());
		$this->assertNull($field->getFilter());
	}

	public function testStructureWrapsUidsUnderNeutralLocale(): void
	{
		$field = $this->createReference();

		$empty = $field->structure();
		$this->assertSame(Reference::class, $empty['type']);
		$this->assertSame([Field::NEUTRAL_LOCALE => []], $empty['value']);

		$filled = $field->structure([['uid' => 'node-a'], ['uid' => 'node-b']]);
		$this->assertSame(
			[['uid' => 'node-a'], ['uid' => 'node-b']],
			$filled['value'][Field::NEUTRAL_LOCALE],
		);
	}

	public function testShapeAcceptsUidList(): void
	{
		$result = $this
			->createReference()
			->shape()
			->validate([
				'type' => Reference::class,
				'value' => [Field::NEUTRAL_LOCALE => [['uid' => 'node-a'], ['uid' => 'node-b']]],
			]);

		$this->assertTrue($result->valid());
		$this->assertSame(
			'node-b',
			$result->values()['value'][Field::NEUTRAL_LOCALE][1]['uid'],
		);
	}

	public function testShapeRejectsItemWithoutUid(): void
	{
		$result = $this
			->createReference()
			->shape()
			->validate([
				'type' => Reference::class,
				'value' => [Field::NEUTRAL_LOCALE => [['label' => 'no uid']]],
			]);

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has(['value', Field::NEUTRAL_LOCALE, 0, 'uid']));
	}

	public function testLimitAttributeMakesFieldSingle(): void
	{
		$owner = new FieldOwner($this->createContext(), 'test-node');
		$field = new Reference('author', $owner, new ValueContext('author', []));
		$field->init(
			Services::withDefaults(),
			new ReflectionProperty(TestNodeWithReference::class, 'author'),
		);

		$this->assertSame(1, $field->getLimitMax());

		$result = $field
			->shape()
			->validate([
				'type' => Reference::class,
				'value' => [Field::NEUTRAL_LOCALE => [['uid' => 'a'], ['uid' => 'b']]],
			]);

		$this->assertFalse($result->valid());
	}

	public function testValueExposesOrderedUids(): void
	{
		$field = $this->createReference('related', [
			'type' => Reference::class,
			'value' => [
				Field::NEUTRAL_LOCALE => [
					['uid' => 'node-a'],
					['uid' => ''],
					['uid' => 'node-b'],
					['label' => 'dropped'],
				],
			],
		]);

		$value = $field->value();

		$this->assertSame(['node-a', 'node-b'], $value->uids());
		$this->assertSame('node-a', $value->uid());
		$this->assertSame(2, $value->count());
		$this->assertTrue($value->isset());
		$this->assertSame(['node-a', 'node-b'], iterator_to_array($value));
	}

	public function testEmptyValueIsNotSet(): void
	{
		$value = $this->createReference()->value();

		$this->assertFalse($value->isset());
		$this->assertNull($value->uid());
		$this->assertSame([], $value->uids());
		$this->assertSame('', (string) $value);
	}

	public function testTargetsAndFilterAttributesApply(): void
	{
		$owner = new FieldOwner($this->createContext(), 'test-node');
		$field = new Reference('related', $owner, new ValueContext('related', []));
		$field->init(
			Services::withDefaults(),
			new ReflectionProperty(TestNodeWithReference::class, 'related'),
		);

		$this->assertSame([TestArticle::class], $field->targets());
		$this->assertSame("type = 'test-article'", $field->getFilter());
	}

	public function testFilterAttributeRejectsNonFilterableField(): void
	{
		$node = new class {
			#[\Cosray\Schema\Filter("type = 'x'")]
			protected Text $title;
		};

		$owner = new FieldOwner($this->createContext(), 'test-node');
		$field = new Text('title', $owner, new ValueContext('title', []));

		$this->throws(RuntimeException::class);

		$field->init(Services::withDefaults(), new ReflectionProperty($node, 'title'));
	}
}

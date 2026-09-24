<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Finder\Order;
use Cosray\Finder\SortField;
use Cosray\Node\Wrapper;
use Cosray\Tests\IntegrationTestCase;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;

final class FinderOrderTest extends IntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
	}

	public static function typedValues(): iterable
	{
		yield 'numbers' => [SortField::number('value'), [10, -3, 2], ['b', 'c', 'a']];
		yield 'precise decimals' => [
			SortField::number('value'),
			['2.00000000000000000002', '-0.5', '2.00000000000000000001'],
			['b', 'c', 'a'],
		];
		yield 'dates' => [SortField::date('value'), ['2027-01-01', '2026-12-31', '2026-01-01'], ['c', 'b', 'a']];
		yield 'instants' => [
			SortField::dateTime('value'),
			['2026-01-01T10:00:00Z', '2026-01-01T11:00:00+02:00', '2026-01-01T08:30:00-02:00'],
			['b', 'a', 'c'],
		];
		yield 'text' => [SortField::text('value'), ['Beta', 'Gamma', 'Alpha'], ['c', 'a', 'b']];
	}

	#[DataProvider('typedValues')]
	public function testTypedOrderingBeforePagination(SortField $field, array $values, array $ascending): void
	{
		$type = $this->createTestType('ordered-test-page');
		foreach (array_combine(['a', 'b', 'c', 'd', 'e', 'g'], [...$values, '', null, " \t\n\r\v"]) as $uid => $value) {
			$this->createTestNode([
				'uid' => $uid,
				'type' => $type,
				'content' => ['value' => ['type' => 'text', 'value' => ['zxx' => $value]]],
			]);
		}
		// A different type need not declare this field at all.
		$this->createTestNode(['uid' => 'f', 'type' => $this->createTestType('limit-test-page')]);
		$cms = $this->createCms();

		foreach (['asc' => $ascending, 'desc' => array_reverse($ascending)] as $direction => $expected) {
			$expected = [...$expected, 'd', 'e', 'f', 'g'];
			$actual = [];
			for ($offset = 0; $offset < count($expected); $offset += 2) {
				$nodes = $cms
					->nodes()
					->only('a', 'b', 'c', 'd', 'e', 'f', 'g')
					->order(new Order($field, $direction), new Order('uid'))
					->offset($offset)
					->limit(2);
				array_push($actual, ...array_map(
					static fn(Wrapper $node): string => $node->meta->uid,
					iterator_to_array($nodes),
				));
			}
			$this->assertSame($expected, $actual);
		}
	}

	public static function invalidValues(): iterable
	{
		yield 'bad number' => [SortField::number('value'), 'not a number'];
		yield 'nonfinite number' => [SortField::number('value'), 'NaN'];
		yield 'relative date' => [SortField::date('value'), 'tomorrow'];
		yield 'impossible date' => [SortField::date('value'), '2026-02-30'];
		yield 'datetime without offset' => [SortField::dateTime('value'), '2026-01-01T10:00:00'];
		yield 'overflowing time' => [SortField::dateTime('value'), '2026-01-01T24:00:00Z'];
		yield 'array' => [SortField::text('value'), ['reference']];
		yield 'object' => [SortField::text('value'), ['nested' => 'value']];
	}

	#[DataProvider('invalidValues')]
	public function testMalformedValuesFailRatherThanSortingSilently(SortField $field, mixed $value): void
	{
		$this->createTestNode([
			'uid' => 'invalid-sort',
			'type' => $this->createTestType('ordered-test-page'),
			'content' => ['value' => ['type' => 'text', 'value' => ['zxx' => $value]]],
		]);

		$this->expectException(PDOException::class);
		iterator_to_array($this->createCms()->nodes()->only('invalid-sort')->order(new Order($field)));
	}
}

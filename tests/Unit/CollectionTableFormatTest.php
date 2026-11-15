<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionTable;
use Cosray\Panel\CollectionUrls;
use Cosray\Tests\TestCase;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;

final class CollectionTableFormatTest extends TestCase
{
	/** @return array<string, array{Column, mixed, string, string}> */
	public static function valueProvider(): array
	{
		return [
			'date in English' => [Column::date('Day', 'day'), '2026-11-05', 'en', 'Nov 5, 2026'],
			'date in British English' => [Column::date('Day', 'day'), '2026-11-05', 'en_GB', '5 Nov 2026'],
			'date in German' => [Column::date('Day', 'day'), '2026-11-05', 'de', '05.11.2026'],
			'date and time in German' => [
				Column::dateTime('Start', 'start'),
				'2026-11-05T16:42:00+00:00',
				'de',
				'05.11.2026, 16:42',
			],
			'number in English' => [Column::number('Amount', 'amount'), '1234567.25', 'en', '1,234,567.25'],
			'number in German' => [Column::number('Amount', 'amount'), '1234567.25', 'de', '1.234.567,25'],
			'negative number' => [Column::number('Amount', 'amount'), '-0.5', 'de', '-0,5'],
			'every stored decimal' => [Column::number('Amount', 'amount'), '1.123456789', 'en', '1.123456789'],
			'integer' => [Column::number('Amount', 'amount'), 1234, 'de', '1.234'],
			'not a number' => [Column::number('Amount', 'amount'), 'n/a', 'de', 'n/a'],
			'text stays as stored' => [Column::text('Code', 'code'), '1234.5', 'de', '1234.5'],
		];
	}

	#[DataProvider('valueProvider')]
	public function testValuesFollowTheColumnKindAndLocale(
		Column $column,
		mixed $value,
		string $locale,
		string $expected,
	): void {
		$table = CollectionTable::from(
			[Column::text('Title', 'title'), $column],
			[['uid' => 'entry', 'columns' => [['value' => 'Entry'], ['value' => $value]]]],
			new CollectionUrls('/panel', 'content', new CollectionQuery()),
			new CollectionListMeta(),
			$locale,
			new DateTimeZone('UTC'),
		);

		$this->assertSame($expected, $table->rows[0]['cells'][1]['value']);
	}

	public function testNumbersAlignRight(): void
	{
		$table = CollectionTable::from(
			[Column::text('Title', 'title'), Column::number('Amount', 'amount')],
			[['uid' => 'entry', 'columns' => [['value' => 'Entry'], ['value' => 3]]]],
			new CollectionUrls('/panel', 'content', new CollectionQuery()),
			new CollectionListMeta(),
			'en',
			new DateTimeZone('UTC'),
		);

		$this->assertStringContainsString('cms-list-col-number', $table->headers[1]['class']);
		$this->assertStringContainsString('cms-list-col-number', $table->rows[0]['cells'][1]['class']);
	}
}

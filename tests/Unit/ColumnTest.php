<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Column;
use Cosray\DateColumn;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Tests\TestCase;
use stdClass;

final class ColumnTest extends TestCase
{
	public function testATextColumnResolvesItsValueAndStyles(): void
	{
		$column = Column::new('Computed', static fn(Wrapper $node): string => 'value')->bold()->italic();

		$this->assertSame('Computed', $column->title);
		$this->assertSame(
			['value' => 'value', 'bold' => true, 'italic' => true],
			$column->get(new Wrapper(new stdClass(), [], new Types())),
		);
	}

	public function testKindFollowsTheColumnType(): void
	{
		$this->assertSame('text', Column::new('Text', 'title')->kind());
		$this->assertSame('date', Column::date('Date', 'start')->kind());
		$this->assertSame('user', Column::editor()->kind());
		$this->assertSame('status', Column::status()->kind());
	}

	public function testChangedAndCreatedSortLatestFirst(): void
	{
		$changed = Column::changed('Changed');
		$created = Column::created('Created');

		$this->assertInstanceOf(DateColumn::class, $changed);
		$this->assertSame('meta.changed', $changed->field);
		$this->assertSame(['changed', 'desc'], [$changed->sort?->key, $changed->sort?->direction]);
		$this->assertSame('meta.created', $created->field);
		$this->assertSame(['created', 'desc'], [$created->sort?->key, $created->sort?->direction]);
		$this->assertNull(Column::changed()->sort(null)->sort);
	}

	public function testFluentSortSetter(): void
	{
		$column = Column::new('Title', 'title')
			->sort(' title ');

		$this->assertSame('title', $column->sort?->key);
	}

	public function testNullSortDisablesSorting(): void
	{
		$column = Column::new('Title', 'title')
			->sort('title')->sort(null);

		$this->assertNull($column->sort);
	}
}

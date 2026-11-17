<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Column;
use Cosray\DateColumn;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Tests\Fixtures\Node\TestHierarchyChild;
use Cosray\Tests\TestCase;
use stdClass;

final class ColumnTest extends TestCase
{
	public function testATextColumnResolvesItsValueAndStyles(): void
	{
		$column = Column::text('Computed', static fn(Wrapper $node): string => 'value')
			->bold()
			->italic();

		$this->assertSame('Computed', $column->title);
		$this->assertSame(
			['value' => 'value', 'bold' => true, 'italic' => true],
			$column->get(new Wrapper(new stdClass(), [], new Types())),
		);
	}

	public function testATypeColumnShowsTheTranslatedLabel(): void
	{
		$dir = sys_get_temp_dir() . '/cosray-column-' . bin2hex(random_bytes(4));
		mkdir($dir);
		file_put_contents(
			$dir . '/app.de.php',
			"<?php return ['messages' => ['Hierarchy Child' => 'Hierarchie-Kind']];",
		);
		Verba::activate(new Translator('de', ['app' => $dir]));

		try {
			$this->assertSame(
				'Hierarchie-Kind',
				Column::type()->get(new Wrapper(new TestHierarchyChild(), [], new Types()))['value'],
			);
		} finally {
			Verba::deactivate();
			unlink($dir . '/app.de.php');
			rmdir($dir);
		}
	}

	public function testKindFollowsTheColumnType(): void
	{
		$this->assertSame('text', Column::text('Text', 'title')->kind());
		$this->assertSame('number', Column::number('Amount', 'amount')->kind());
		$this->assertSame('date', Column::date('Date', 'start')->kind());
		$this->assertSame('datetime', Column::dateTime('Start', 'start')->kind());
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

	public function testASortWithoutFieldsOrdersAsTheColumnsKind(): void
	{
		$this->assertSame('text', Column::text('Name', 'name')->sort('name')->sort?->fields[0]->type);
		$this->assertSame('numeric', Column::number('Amount', 'amount')->sort('amount')->sort?->fields[0]->type);
		$this->assertSame('date', Column::date('Day', 'day')->sort('day')->sort?->fields[0]->type);
		$this->assertSame('timestamptz', Column::dateTime('Start', 'start')->sort('start')->sort?->fields[0]->type);
		$this->assertSame('text', Column::changed()->sort?->fields[0]->type);
		$this->assertSame(
			'text',
			Column::number('Amount', 'amount')->sort('amount', ['amount'])->sort?->fields[0]->type,
		);
	}

	public function testFluentSortSetter(): void
	{
		$column = Column::text('Title', 'title')
			->sort(' title ');

		$this->assertSame('title', $column->sort?->key);
	}

	public function testNullSortDisablesSorting(): void
	{
		$column = Column::text('Title', 'title')->sort('title')->sort(null);

		$this->assertNull($column->sort);
	}

	public function testTheSearchMatchesTitleAndTypeColumnsByDefault(): void
	{
		$this->assertSame(['title'], Column::text('Name', 'title')->searched());
		$this->assertSame(['type'], Column::type()->searched());
		$this->assertSame([], Column::text('Code', 'code')->searched());
		$this->assertSame([], Column::editor()->searched());
		$this->assertSame([], Column::type()->search(false)->searched());
	}

	public function testAColumnJoinsTheSearchWithWhatItShowsOrTheFieldsNamed(): void
	{
		$this->assertSame(['code'], Column::text('Code', 'code')->search()->searched());
		$this->assertSame(['type'], Column::text('Kind', 'meta.name')->search()->searched());
		$this->assertSame(['uid'], Column::text('Id', 'meta.uid')->search()->searched());
		$this->assertSame(['amount'], Column::number('Amount', 'amount')->search()->searched());
		$this->assertSame(
			['venue.city', 'venue.name'],
			Column::text('Venue', static fn(Wrapper $node): string => '')
				->search('venue.city', 'venue.name')
				->searched(),
		);
	}

	public function testAComputedColumnNamesTheFieldsItSearches(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Column 'Venue' shows no field the search can match");

		Column::text('Venue', static fn(Wrapper $node): string => '')->search();
	}

	public function testFalseLeavesAColumnOutAlone(): void
	{
		$this->expectException(RuntimeException::class);

		Column::text('Code', 'code')->search(false, 'code');
	}
}

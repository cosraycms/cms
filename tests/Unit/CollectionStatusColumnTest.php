<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Cms;
use Cosray\Collection\Listing;
use Cosray\Collection\Schemas;
use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Context;
use Cosray\Contract\Columns;
use Cosray\Field\Services;
use Cosray\Node\Types;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionTable;
use Cosray\Panel\CollectionUrls;
use Cosray\Stage;
use Cosray\StatusColumn;
use Cosray\Tests\TestCase;
use DateTimeZone;

final class StatusColumnTestCollection implements Columns
{
	public array $configured = [];

	public function columns(): array
	{
		return $this->configured;
	}
}

final class CollectionStatusColumnTest extends TestCase
{
	public function testStatusColumnsKeepTheStagesInTheirOrder(): void
	{
		$this->assertSame(Stage::cases(), Column::status()->stages);
		$this->assertSame([Stage::Main, Stage::Mine], Column::status(Stage::Mine, Stage::Main, Stage::Mine)->stages);
	}

	public function testStagesFollowTheFirstColumnUnlessPlaced(): void
	{
		$collection = new StatusColumnTestCollection();
		$collection->configured = [
			Column::new('Title', 'title')->sort('title'),
			Column::new('Changed', 'meta.changed'),
		];
		$columns = $this->listing($collection)->columns;

		$this->assertCount(3, $columns);
		$this->assertInstanceOf(StatusColumn::class, $columns[1]);

		$placed = Column::status(Stage::Main);
		$collection->configured = [$placed, Column::new('Title', 'title')->sort('title')];

		$this->assertSame($placed, $this->listing($collection)->columns[0]);
		$this->assertCount(2, $this->listing($collection)->columns);
	}

	public function testStatusColumnsShareTheStagesAndTheFirstFieldLeads(): void
	{
		$table = $this->table(
			[
				Column::status(Stage::Main),
				Column::new('Title', 'title'),
				Column::status(Stage::Draft, Stage::Mine)->present(),
			],
			draft: true,
		);
		$cells = $table->rows[0]['cells'];

		$this->assertSame([false, true, false], array_column($table->headers, 'lead'));
		$this->assertSame(['main'], array_column($cells[0]['status']['stages'] ?? [], 'key'));
		$this->assertNotNull($cells[1]['editUrl']);
		$this->assertNull($cells[0]['editUrl']);
		$this->assertSame(['draft'], array_column($cells[2]['status']['stages'] ?? [], 'key'));
	}

	public function testHiddenAndLockedJoinTheMainVersion(): void
	{
		$table = $this->table([
			Column::status(Stage::Draft),
			Column::new('Title', 'title'),
			Column::status(Stage::Main),
		]);
		$cells = $table->rows[0]['cells'];

		$this->assertSame([], $cells[0]['status']['badges'] ?? null);
		$this->assertSame(['hidden'], array_column($cells[2]['status']['badges'] ?? [], 'kind'));
	}

	public function testCompactNamesEveryStageItStandsFor(): void
	{
		$cells = $this->table([
			Column::new('Title', 'title'),
			Column::status()->compact()->present(),
		])->rows[0]['cells'];

		$this->assertSame(['main'], array_column($cells[1]['status']['stages'] ?? [], 'key'));
		$this->assertStringContainsString('status:draft', $cells[1]['status']['sentence'] ?? '');
	}

	public function testARecordsSavedStateIsItsMainVersion(): void
	{
		$cells = $this->table([
			Column::new('Title', 'title'),
			Column::status(Stage::Main),
		], record: true)->rows[0]['cells'];

		$this->assertSame(['saved'], array_column($cells[1]['status']['stages'] ?? [], 'key'));
	}

	/** @param list<Column|StatusColumn> $columns */
	private function table(array $columns, bool $draft = false, bool $record = false): CollectionTable
	{
		$node = [
			'uid' => 'entry',
			'published' => true,
			'hidden' => true,
			'stages' => [
				'record' => $record,
				'edited' => '2026-08-11 22:25:00+00',
				'editor' => 'Ada',
				'draft' => $draft ? ['changed' => '2026-08-11 22:30:00+00', 'editorName' => 'Ada'] : null,
				'mine' => null,
			],
			'columns' => array_map(
				static fn(Column|StatusColumn $column): array => $column instanceof Column ? ['value' => 'Entry'] : [],
				$columns,
			),
		];

		return CollectionTable::from(
			$columns,
			[$node],
			new CollectionUrls('/panel', 'content', new CollectionQuery()),
			new CollectionListMeta(showHidden: true),
			'en',
			new DateTimeZone('UTC'),
		);
	}

	private function listing(StatusColumnTestCollection $collection): Listing
	{
		$context = new Context($this->db(), $this->request(), $this->config(), $this->container(), $this->factory());

		return new Listing(
			new Schemas()->of($collection::class),
			new Cms($context, Services::withDefaults()),
			new Types(),
			$collection,
		);
	}
}

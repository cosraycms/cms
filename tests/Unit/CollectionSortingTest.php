<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Cms;
use Cosray\Collection\Listing;
use Cosray\Collection\Schemas;
use Cosray\Collection\Sort;
use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Context;
use Cosray\Contract\Columns;
use Cosray\Contract\Entries;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Finder\Nodes;
use Cosray\Finder\SortField;
use Cosray\Node\Types;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionTable;
use Cosray\Panel\CollectionUrls;
use Cosray\Tests\TestCase;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;

final class SortingTestCollection implements Columns, Entries
{
	public array $configured = [];

	public function columns(): array
	{
		return $this->configured;
	}

	public function entries(Nodes $nodes): Nodes
	{
		throw new RuntimeException('Query must not run for invalid configuration');
	}
}

final class CollectionSortingTest extends TestCase
{
	public function testDuplicateKeysFailBeforeQuerying(): void
	{
		$collection = new SortingTestCollection();
		$collection->configured = [
			Column::new('Title', 'title')->sort('title'),
			Column::new('Again', 'title')->sort('title'),
		];
		$this->expectExceptionMessage("Duplicate collection sort key 'title'");
		$this->listing($collection)->list();
	}

	public static function defaultSorts(): iterable
	{
		yield 'flagged column' => [
			[
				Column::new('Title', 'title')->sort('title'),
				Column::new('Changed', 'meta.changed')->sort('changed', direction: 'desc', default: true),
			],
			'changed',
		];
		yield 'first sortable column' => [
			[
				Column::new('Editor', 'meta.editor'),
				Column::new('Name', 'title')->sort('name', ['title']),
				Column::new('Changed', 'meta.changed')->sort('changed', direction: 'desc'),
			],
			'name',
		];
	}

	#[DataProvider('defaultSorts')]
	public function testDefaultSortIsTheFlaggedOrFirstSortableColumn(array $columns, string $expected): void
	{
		$collection = new SortingTestCollection();
		$collection->configured = $columns;
		$this->assertSame($expected, $this->listing($collection)->defaultSort);
	}

	public function testSeveralDefaultsFailBeforeQuerying(): void
	{
		$collection = new SortingTestCollection();
		$collection->configured = [
			Column::new('Title', 'title')->sort('title', default: true),
			Column::new('Changed', 'meta.changed')->sort('changed', default: true),
		];
		$this->expectExceptionMessage("Only one collection sort can be the default, found 'title', 'changed'");
		$this->listing($collection)->list();
	}

	public function testUnsortableListingsFailBeforeQuerying(): void
	{
		$collection = new SortingTestCollection();
		$collection->configured = [Column::new('Title', 'title')];
		$this->expectExceptionMessage('Collection listings need at least one sortable column');
		$this->listing($collection)->list();
	}

	public static function invalidSelections(): iterable
	{
		yield 'unknown sort' => ['0', '', "Unknown collection sort '0'"];
		yield 'invalid direction' => ['', '0', "Invalid sort direction '0'"];
	}

	#[DataProvider('invalidSelections')]
	public function testInvalidDirectSelectionsFailBeforeQuerying(
		string $sort,
		string $direction,
		string $message,
	): void {
		$collection = new SortingTestCollection();
		$collection->configured = [Column::new('Title', 'title')->sort('title')];
		$this->expectExceptionMessage($message);
		$this->listing($collection)->list(sort: $sort, dir: $direction);
	}

	public static function invalidDefinitions(): iterable
	{
		yield 'empty key' => ['', null];
		yield 'empty fields' => ['name', []];
		yield 'field map' => ['name', ['name' => 'lastName']];
		yield 'callback field' => ['name', [static fn(): string => 'value']];
		yield 'SQL expression' => ['name', ['lastName DESC']];
		yield 'direction' => ['name', null, 'sideways'];
	}

	#[DataProvider('invalidDefinitions')]
	public function testInvalidDefinitionsFail(string $key, ?array $fields, string $direction = 'asc'): void
	{
		$this->expectException(RuntimeException::class);
		new Sort($key, $fields, $direction);
	}

	public function testCompoundTermsFollowDirectionAndHaveOneUniqueTieBreaker(): void
	{
		$sort = new Sort('name', ['lastName', 'firstName']);
		$order = $sort->order('desc');
		$this->assertSame(
			['lastName', 'firstName', 'uid'],
			array_map(static fn($term): string => $term->field->name, $order),
		);
		$this->assertSame(['desc', 'desc', 'asc'], array_column($order, 'direction'));
		$this->assertCount(1, new Sort('identifier', ['uid'])->order());
		$this->assertSame('numeric', new Sort('amount', [SortField::number('amount')])->order()[0]->field->type);
	}

	public function testHeadersUseExplicitSortDefinitionsAndResetOffset(): void
	{
		$columns = [
			Column::new('Title', 'title')->sort('title'),
			Column::new('Changed', 'meta.changed')->sort('changed', direction: 'desc'),
			Column::new('Unsorted title', 'title')->sort('title')->sort(null),
		];
		$urls = new CollectionUrls(
			'/cp',
			'content',
			new CollectionQuery(q: 'term', sort: 'title', dir: 'asc', offset: 20, limit: 10),
		);
		$table = CollectionTable::from($columns, [], $urls, new CollectionListMeta(), 'en', new DateTimeZone('UTC'));
		$this->assertSame('/cp/collection/content?q=term&sort=title&dir=desc&limit=10', $table->headers[0]['url']);
		$this->assertSame('ascending', $table->headers[0]['ariaSort']);
		$this->assertSame('/cp/collection/content?q=term&sort=changed&dir=desc&limit=10', $table->headers[1]['url']);
		$this->assertNull($table->headers[1]['ariaSort']);
		$this->assertNull($table->headers[2]['url']);
	}

	private function listing(SortingTestCollection $collection): Listing
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

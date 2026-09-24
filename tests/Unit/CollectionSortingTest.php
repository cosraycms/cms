<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Collection;
use Cosray\Collection\Listing;
use Cosray\Collection\Sort;
use Cosray\CollectionListMeta;
use Cosray\Column;
use Cosray\Exception\RuntimeException;
use Cosray\Finder\Nodes;
use Cosray\Finder\SortField;
use Cosray\Node\Types;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionTable;
use Cosray\Panel\CollectionUrls;
use Cosray\Tests\TestCase;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;

final class SortingTestCollection extends Collection
{
	public array $configured = [];

	public function columns(): array
	{
		return $this->configured;
	}

	public function entries(): Nodes
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
		new Listing($collection, new Types())->list();
	}

	public function testMissingDefaultFailsBeforeQuerying(): void
	{
		$collection = new SortingTestCollection();
		$collection->configured = [Column::new('Name', 'title')->sort('name', ['title'])];
		$this->expectExceptionMessage("Default collection sort 'title' has no sortable column");
		new Listing($collection, new Types())->list();
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
		new Listing($collection, new Types())->list(sort: $sort, dir: $direction);
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
}

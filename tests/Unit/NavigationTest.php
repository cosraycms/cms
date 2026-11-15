<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Contract\Entries;
use Cosray\Exception\RuntimeException;
use Cosray\Finder\Nodes;
use Cosray\Navigation;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Permission;
use Cosray\SystemNav;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Collection\TestHierarchyCollection;
use Cosray\Tests\Fixtures\Collection\TestStaticIconCollection;
use Cosray\Tests\TestCase;

final class NavigationTest extends TestCase
{
	public function testNestedSectionsBuildRecursiveItemsAndPayload(): void
	{
		$navigation = new Navigation();
		$content = $navigation->section('Content')->icon('bi:folder', ['class' => 'cms-content-icon']);
		$articles = $content->collection(TestArticlesCollection::class)->icon('bi:file-earmark');
		$articles->meta->label = 'Articles';
		$articles->meta->badge = 'new';
		$content
			->section('Nested')
			->collection(TestHierarchyCollection::class);

		$items = $navigation->items();
		$payload = $navigation->payload();

		$this->assertCount(1, $items);
		$this->assertSame('Content', $items[0]->meta->label);
		$this->assertNull($items[0]->slug());
		$this->assertCount(2, $items[0]->children());
		$this->assertSame('Articles', $items[0]->children()[0]->meta->label);
		$this->assertSame('test-articles', $items[0]->children()[0]->slug());
		$this->assertSame('Nested', $items[0]->children()[1]->meta->label);
		$this->assertSame('test-hierarchy', $items[0]->children()[1]->children()[0]->slug());

		$this->assertCount(1, $payload);
		$this->assertSame('section', $payload[0]['type']);
		$this->assertSame('Content', $payload[0]['name']);
		$this->assertCount(2, $payload[0]['children']);
		$this->assertSame('Articles', $payload[0]['children'][0]['name']);
		$this->assertSame('bi:folder', $payload[0]['meta']['icon']);
		$this->assertSame(
			[
				'id' => 'bi:folder',
				'args' => ['class' => 'cms-content-icon'],
			],
			$payload[0]['meta']['iconMeta'],
		);
		$this->assertSame('new', $payload[0]['children'][0]['meta']['badge']);
		$this->assertSame('bi:file-earmark', $payload[0]['children'][0]['meta']['icon']);
		$this->assertSame('section', $payload[0]['children'][1]['type']);
		$this->assertSame('Nested', $payload[0]['children'][1]['name']);
		$this->assertSame(
			'test-hierarchy',
			$payload[0]['children'][1]['children'][0]['slug'],
		);
		$this->assertSame('Articles', $navigation->ref('test-articles')->meta->label);
	}

	public function testDuplicateHandlesAreRejectedAcrossSections(): void
	{
		$navigation = new Navigation();
		$navigation->section('Content')->collection(TestArticlesCollection::class);

		$this->throws(RuntimeException::class, 'Duplicate collection handle: test-articles');
		$navigation->section('Other')->collection(TestArticlesCollection::class);
	}

	public function testOrderedItemsAreSortedAndEmptySectionsAreFiltered(): void
	{
		$navigation = new Navigation();
		$navigation->collection(TestArticlesCollection::class)->meta->order = 20;
		$navigation->collection(TestHierarchyCollection::class)->meta->order = 10;
		$navigation->section('Empty');

		$items = $navigation->items();

		$this->assertCount(2, $items);
		$this->assertSame('test-hierarchy', $items[0]->slug());
		$this->assertSame('test-articles', $items[1]->slug());
	}

	public function testCollectionsMustDeclareWhatTheyList(): void
	{
		$navigation = new Navigation();
		$navigation->collection(EntriesOnlyCollection::class);
		$this->assertSame(EntriesOnlyCollection::class, $navigation->ref('entries-only')->class);

		$this->throws(RuntimeException::class, 'must declare #[Types] or implement ' . Entries::class);
		$navigation->collection(UndeclaredCollection::class);
	}

	public function testUnknownCollectionClassesAreRejected(): void
	{
		$this->throws(RuntimeException::class, "Unknown collection class 'App\\Missing'");
		new Navigation()->collection('App\\Missing');
	}

	public function testEntriesNeedingADeniedPermissionAreLeftOutWithTheirEmptiedSections(): void
	{
		$navigation = new Navigation();
		$navigation->collection(TestArticlesCollection::class);
		$navigation->collection(RestrictedCollection::class);
		$navigation->section('Admin')->link('Logs', '/panel/logs')->permission('read-logs');
		$navigation->section('Help')->link('Guide', '/panel/guide');
		$labels = static fn(array $items): array => array_map(static fn($item) => $item->meta->label, $items);

		$this->assertSame(
			['Test articles', 'Restricted', 'Admin', 'Help'],
			$labels($navigation->items()),
			'without a check, every entry is listed',
		);
		$this->assertSame(
			['Test articles', 'Admin', 'Help'],
			$labels($navigation->items(static fn(string $permission): bool => $permission === 'read-logs')),
		);
		$this->assertSame(
			['Test articles', 'Help'],
			$labels($navigation->items(static fn(string $permission): bool => false)),
		);
	}

	public function testSystemLinksAreOrderedAndHiddenOnesLeftOut(): void
	{
		$system = new SystemNav();
		$system->link('Stats', '/panel/stats')->permission(' view-stats ');
		$system->link('Hidden', '/panel/hidden')->meta->hidden = true;
		$system->link('Logs', '/panel/logs', '/panel/logs');
		$system->link('First', '/panel/first')->meta->order = -1;

		$links = $system->links();

		$this->assertSame(['First', 'Stats', 'Logs'], array_map(static fn($link) => $link->meta->label, $links));
		$this->assertSame('view-stats', $links[1]->meta->permission);
		$this->assertTrue($links[2]->active('/panel/logs/today'));
	}

	public function testNavLinkPermissionsMustNotBeEmpty(): void
	{
		$this->throws(RuntimeException::class, 'Nav link permissions must not be empty');
		new SystemNav()
			->link('Stats', '/panel/stats')
			->permission(' ');
	}

	public function testCollectionIconAttributeIsNormalizedInMeta(): void
	{
		$navigation = new Navigation();
		$navigation->collection(TestStaticIconCollection::class);
		$items = $navigation->items();
		$payload = $navigation->payload();

		$this->assertSame('bi:archive', $items[0]->meta->icon['id']);
		$this->assertSame('bi:archive', $payload[0]['meta']['icon']);
		$this->assertSame(
			[
				'id' => 'bi:archive',
				'args' => [],
			],
			$payload[0]['meta']['iconMeta'],
		);
	}
}

#[Handle('entries-only')]
final class EntriesOnlyCollection implements Entries
{
	public function entries(Nodes $nodes): Nodes
	{
		return $nodes;
	}
}

final class UndeclaredCollection {}

#[Handle('restricted'), Label('Restricted'), Permission('edit-secrets')]
final class RestrictedCollection implements Entries
{
	public function entries(Nodes $nodes): Nodes
	{
		return $nodes;
	}
}

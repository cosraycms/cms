<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Collection;
use Cosray\Collection\Schemas;
use Cosray\Finder\Nodes;
use Cosray\Schema\Badge;
use Cosray\Schema\Blueprints;
use Cosray\Schema\Hidden;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Order;
use Cosray\Tests\Fixtures\Collection\TestHierarchyCollection;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\TestCase;

#[
	Label('Fancy'),
	Badge('beta'),
	Hidden,
	Order(7),
	Listing(published: false, children: true),
	Blueprints(PlainPage::class),
]
final class FancyPagesCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms->nodes();
	}
}

final class BarePagesCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms->nodes();
	}
}

/**
 * @internal
 *
 * @coversNothing
 */
final class CollectionSchemaTest extends TestCase
{
	public function testAttributesResolve(): void
	{
		$schemas = new Schemas();
		$schema = $schemas->of(FancyPagesCollection::class);

		$this->assertSame('Fancy', $schema->label);
		$this->assertSame('beta', $schema->badge);
		$this->assertTrue($schema->hidden);
		$this->assertSame(7, $schema->order);
		$this->assertFalse($schema->listing->showPublished);
		$this->assertTrue($schema->listing->showChildren);
		$this->assertSame([PlainPage::class], $schema->blueprints);
		$this->assertSame('fancy-pages-collection', $schema->handle);
	}

	public function testDefaultsDerive(): void
	{
		$schemas = new Schemas();
		$schema = $schemas->of(BarePagesCollection::class);

		$this->assertSame('bare-pages-collection', $schema->handle);
		$this->assertSame('Bare Pages Collection', $schema->label);
		$this->assertNull($schema->icon);
		$this->assertFalse($schema->hidden);
		$this->assertSame(0, $schema->order);
		$this->assertTrue($schema->listing->showPublished);
		$this->assertSame([], $schema->blueprints);
	}

	public function testCollectionInstanceReadsSchema(): void
	{
		$collection = new FancyPagesCollection();

		$this->assertSame('fancy-pages-collection', $collection->slug());
		$this->assertSame('Fancy', $collection->meta->label);
		$this->assertTrue($collection->listMeta->showChildren);
		$this->assertSame([PlainPage::class], $collection->blueprints());
	}

	public function testBlueprintsMethodOverrideStillWins(): void
	{
		$collection = new TestHierarchyCollection();

		$this->assertNotSame([], $collection->blueprints());
	}
}

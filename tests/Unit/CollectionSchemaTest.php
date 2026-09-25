<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Collection\Schemas;
use Cosray\Exception\RuntimeException;
use Cosray\Schema\Badge;
use Cosray\Schema\Blueprints;
use Cosray\Schema\Hidden;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Order;
use Cosray\Schema\Types;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\TestCase;

#[
	Label('Fancy'),
	Badge('beta'),
	Hidden,
	Order(7),
	Listing(published: false, children: true, search: ['title', 'lastName']),
	Blueprints(PlainPage::class),
	Types('test-page', PlainPage::class),
]
final class FancyPagesCollection {}

final class BarePagesCollection {}

#[Types(' ')]
final class BlankTypesCollection {}

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
		$this->assertSame(['title', 'lastName'], $schema->search);
		$this->assertSame([PlainPage::class], $schema->blueprints);
		$this->assertSame(['test-page', PlainPage::class], $schema->types);
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
		$this->assertSame(['uid', 'title'], $schema->search);
		$this->assertSame([], $schema->blueprints);
		$this->assertSame([], $schema->types);
	}

	public function testBlankTypesFail(): void
	{
		$this->throws(RuntimeException::class, 'must name at least one node type');
		new Schemas()->of(BlankTypesCollection::class);
	}
}

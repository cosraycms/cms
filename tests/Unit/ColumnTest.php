<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Closure;
use Cosray\Column;
use Cosray\Tests\TestCase;

final class ColumnTest extends TestCase
{
	public function testNewFactoryMethod(): void
	{
		$column = Column::new('Title', 'title');

		$this->assertSame('Title', $column->title);
		$this->assertSame('title', $column->field);
	}

	public function testConstructorDirectly(): void
	{
		$column = new Column('Name', 'name');

		$this->assertSame('Name', $column->title);
		$this->assertSame('name', $column->field);
	}

	public function testFluentBoldSetter(): void
	{
		$column = Column::new('Title', 'title')
			->bold(true);

		// We can't directly test private properties, but we can verify
		// the fluent interface returns the same instance
		$this->assertInstanceOf(Column::class, $column);
	}

	public function testFluentItalicSetter(): void
	{
		$column = Column::new('Title', 'title')
			->italic(true);

		$this->assertInstanceOf(Column::class, $column);
	}

	public function testFluentBadgeSetter(): void
	{
		$column = Column::new('Title', 'title')
			->badge(true);

		$this->assertInstanceOf(Column::class, $column);
	}

	public function testFluentDateSetter(): void
	{
		$column = Column::new('Title', 'title')
			->date(true);

		$this->assertInstanceOf(Column::class, $column);
	}

	public function testFluentSortSetter(): void
	{
		$column = Column::new('Title', 'title')
			->sort('title');

		$this->assertSame('title', $column->sortKey());
	}

	public function testEmptySortDisablesSorting(): void
	{
		$column = Column::new('Title', 'title')
			->sort('');

		$this->assertNull($column->sortKey());
	}

	public function testChainedFluentSetters(): void
	{
		$column = Column::new('Title', 'title')
			->bold(true)
			->italic(true)
			->badge(true)
			->date(true)
			->sort('title');

		$this->assertInstanceOf(Column::class, $column);
	}

	public function testFieldCanBeClosure(): void
	{
		$column = new Column('Computed', static fn($node) => $node->title());

		$this->assertSame('Computed', $column->title);
		$this->assertInstanceOf(Closure::class, $column->field);
	}
}

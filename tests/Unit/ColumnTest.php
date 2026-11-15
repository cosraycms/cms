<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Column;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Tests\TestCase;
use stdClass;

final class ColumnTest extends TestCase
{
	public function testConfiguredValueAndStylesAreResolved(): void
	{
		$column = Column::new('Computed', static fn(Wrapper $node): string => 'value')
			->bold(true)
			->italic(static fn(Wrapper $node): bool => true)
			->badge(true)
			->date(static fn(Wrapper $node): bool => true)
			->avatar(static fn(Wrapper $node): bool => false)
			->name(true);

		$this->assertSame('Computed', $column->title);
		$this->assertSame(
			[
				'value' => 'value',
				'bold' => true,
				'italic' => true,
				'badge' => true,
				'date' => true,
				'avatar' => false,
				'name' => true,
				'color' => '',
			],
			$column->get(new Wrapper(new stdClass(), [], new Types())),
		);
	}

	public function testKindFollowsStaticDisplayMode(): void
	{
		$this->assertSame('text', Column::new('Text', 'title')->kind());
		$this->assertSame('badge', Column::new('Badge', 'title')->badge(true)->kind());
		$this->assertSame('date', Column::new('Date', 'title')->date(true)->kind());
		$this->assertSame('avatar', Column::new('Editor', 'meta.editor')->avatar(true)->kind());
		$this->assertSame('text', Column::new('Editor', 'meta.editor')->avatar(static fn(): bool => true)->kind());
		$this->assertSame('text', Column::new('Editor', 'meta.editor')->kind());
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

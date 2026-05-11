<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Category;
use Celemas\Cms\Tests\TestCase;

final class CategoryTest extends TestCase
{
	public function testConstructorSetsProperties(): void
	{
		$name = 'blog';
		$title = 'Blog Posts';
		$categories = ['news', 'articles', 'tutorials'];

		$category = new Category($name, $title, $categories);

		$this->assertSame($name, $category->name);
		$this->assertSame($title, $category->title);
		$this->assertSame($categories, $category->categories);
	}

	public function testEmptyCategories(): void
	{
		$category = new Category('empty', 'Empty Category', []);

		$this->assertSame([], $category->categories);
	}

	public function testNestedCategories(): void
	{
		$nested = [
			[
				'name' => 'parent',
				'title' => 'Parent',
				'categories' => [
					['name' => 'child', 'title' => 'Child'],
				],
			],
		];

		$category = new Category('nested', 'Nested Category', $nested);
		$this->assertSame($nested, $category->categories);
	}
}

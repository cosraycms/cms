<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Integration;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Finder\Menu;
use Celemas\Cms\Tests\IntegrationTestCase;

/**
 * Integration tests for Menu finder.
 *
 * @internal
 *
 * @coversNothing
 */
final class MenuFinderTest extends IntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
		$this->createTestMenu();
	}

	private function createTestMenu(): void
	{
		// Create a test menu
		$this->db()->execute(
			"INSERT INTO cms.menus (menu, description) VALUES ('test-menu', 'Test Menu')",
		)->run();

		// Create menu items
		$items = [
			[
				'item' => 'home',
				'parent' => null,
				'displayorder' => 1,
				'data' => [
					'type' => 'page',
					'title' => ['en' => 'Home', 'de' => 'Startseite'],
					'path' => ['en' => '/', 'de' => '/de/'],
				],
			],
			[
				'item' => 'about',
				'parent' => null,
				'displayorder' => 2,
				'data' => [
					'type' => 'page',
					'title' => ['en' => 'About', 'de' => 'Über uns'],
					'path' => ['en' => '/about', 'de' => '/ueber-uns'],
				],
			],
			[
				'item' => 'about.team',
				'parent' => 'about',
				'displayorder' => 1,
				'data' => [
					'type' => 'page',
					'title' => ['en' => 'Team', 'de' => 'Team'],
					'path' => ['en' => '/about/team', 'de' => '/ueber-uns/team'],
				],
			],
			[
				'item' => 'contact',
				'parent' => null,
				'displayorder' => 3,
				'data' => [
					'type' => 'page',
					'title' => ['en' => 'Contact', 'de' => 'Kontakt'],
					'path' => ['en' => '/contact', 'de' => '/kontakt'],
					'class' => 'contact-link',
				],
			],
		];

		foreach ($items as $item) {
			$this->db()->execute(
				'INSERT INTO cms.menuitems (item, parent, menu, displayorder, data) VALUES (:item, :parent, :menu, :displayorder, :data::jsonb)',
				[
					'item' => $item['item'],
					'parent' => $item['parent'],
					'menu' => 'test-menu',
					'displayorder' => $item['displayorder'],
					'data' => json_encode($item['data']),
				],
			)->run();
		}
	}

	protected function tearDown(): void
	{
		// Clean up menu items
		$this->db()->execute("DELETE FROM cms.menuitems WHERE menu = 'test-menu'")->run();
		$this->db()->execute("DELETE FROM cms.menus WHERE menu = 'test-menu'")->run();

		parent::tearDown();
	}

	public function testMenuCreation(): void
	{
		$context = $this->createContext();
		$menu = new Menu($context, 'test-menu');

		$this->assertInstanceOf(Menu::class, $menu);
	}

	public function testMenuThrowsExceptionForNonExistentMenu(): void
	{
		$context = $this->createContext();

		$this->throws(RuntimeException::class, 'Menu not found');
		new Menu($context, 'non-existent-menu');
	}

	public function testMenuIterationReturnsMenuItems(): void
	{
		$context = $this->createContext();
		$menu = new Menu($context, 'test-menu');

		$items = [];
		foreach ($menu as $key => $item) {
			$items[$key] = $item;
		}

		$this->assertCount(3, $items);
		$this->assertArrayHasKey('home', $items);
		$this->assertArrayHasKey('about', $items);
		$this->assertArrayHasKey('contact', $items);
	}

	public function testMenuItemProperties(): void
	{
		$context = $this->createContext();
		$menu = new Menu($context, 'test-menu');

		$menu->rewind();
		$home = $menu->current();

		$this->assertEquals('Home', $home->title());
		$this->assertEquals('/', $home->path());
		$this->assertEquals('page', $home->type());
		$this->assertEquals(1, $home->level());
		$this->assertFalse($home->hasChildren());
	}

	public function testMenuItemWithChildren(): void
	{
		$context = $this->createContext();
		$menu = new Menu($context, 'test-menu');

		// Navigate to 'about' item
		$menu->rewind();
		$menu->next();
		$about = $menu->current();

		$this->assertEquals('About', $about->title());
		$this->assertTrue($about->hasChildren());

		// Check child items - the tree structure creates them
		$children = iterator_to_array($about->children());
		$this->assertGreaterThanOrEqual(1, count($children));
		$this->assertEquals('Team', $children[0]->title());
		$this->assertEquals('/about/team', $children[0]->path());
		$this->assertEquals(2, $children[0]->level());
	}

	public function testMenuItemWithCustomClass(): void
	{
		$context = $this->createContext();
		$menu = new Menu($context, 'test-menu');

		// Navigate to 'contact' item (3rd item)
		$menu->rewind();
		$menu->next();
		$menu->next();
		$contact = $menu->current();

		$this->assertEquals('contact-link', $contact->class());
	}

	public function testMenuHtmlGeneration(): void
	{
		$context = $this->createContext();
		$menu = new Menu($context, 'test-menu');

		$html = $menu->html('main-menu', 'nav');

		// The HTML contains the menu structure with proper elements
		$this->assertStringContainsString('<nav', $html);
		$this->assertStringContainsString('</nav>', $html);
		$this->assertStringContainsString('<ul', $html);
		$this->assertStringContainsString('Home', $html);
		$this->assertStringContainsString('href="/"', $html);
		$this->assertStringContainsString('contact-link', $html);
	}

	public function testMenuItemWithoutImageReturnsNull(): void
	{
		$context = $this->createContext();
		$menu = new Menu($context, 'test-menu');

		$menu->rewind();
		$home = $menu->current();

		$this->assertNull($home->image());
	}

	public function testMenuItemIteratorInterface(): void
	{
		$context = $this->createContext();
		$menu = new Menu($context, 'test-menu');

		$menu->rewind();
		$this->assertTrue($menu->valid());

		$menu->next();
		$this->assertTrue($menu->valid());

		$menu->next();
		$this->assertTrue($menu->valid());

		$menu->next();
		$this->assertFalse($menu->valid());
	}
}

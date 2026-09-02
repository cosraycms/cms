<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Exception\RuntimeException;
use Cosray\Finder\Menu;
use Cosray\Tests\IntegrationTestCase;

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
			"INSERT INTO cms.menus (menu, description) VALUES ('test-menu', '{\"zxx\": \"Test Menu\"}')",
		)->run();

		// Create menu items
		$items = [
			[
				'item' => 'home',
				'parent' => null,
				'position' => 1,
				'data' => [
					'type' => 'node',
					'title' => ['en' => 'Home', 'de' => 'Startseite'],
					'path' => ['en' => '/', 'de' => '/de/'],
				],
			],
			[
				'item' => 'about',
				'parent' => null,
				'position' => 2,
				'data' => [
					'type' => 'node',
					'title' => ['en' => 'About', 'de' => 'Über uns'],
					'path' => ['en' => '/about', 'de' => '/ueber-uns'],
				],
			],
			[
				'item' => 'about.team',
				'parent' => 'about',
				'position' => 1,
				'data' => [
					'type' => 'node',
					'title' => ['en' => 'Team', 'de' => 'Team'],
					'path' => ['en' => '/about/team', 'de' => '/ueber-uns/team'],
				],
			],
			[
				'item' => 'contact',
				'parent' => null,
				'position' => 3,
				'data' => [
					'type' => 'node',
					'title' => ['en' => 'Contact', 'de' => 'Kontakt'],
					'path' => ['en' => '/contact', 'de' => '/kontakt'],
					'class' => 'contact-link',
				],
			],
		];

		foreach ($items as $item) {
			$this->db()->execute(
				'INSERT INTO cms.menu_items (item, parent, menu, position, data) VALUES (:item, :parent, :menu, :position, :data::jsonb)',
				[
					'item' => $item['item'],
					'parent' => $item['parent'],
					'menu' => 'test-menu',
					'position' => $item['position'],
					'data' => json_encode($item['data']),
				],
			)->run();
		}
	}

	protected function tearDown(): void
	{
		// Clean up menu items
		$this->db()->execute("DELETE FROM cms.menu_items WHERE menu = 'test-menu'")->run();
		$this->db()->execute("DELETE FROM cms.menus WHERE menu = 'test-menu'")->run();

		parent::tearDown();
	}

	private function insertItem(string $item, array $data, int $position = 10): void
	{
		$this->db()->execute(
			'INSERT INTO cms.menu_items (item, parent, menu, position, data)
			VALUES (:item, NULL, :menu, :position, :data::jsonb)',
			[
				'item' => $item,
				'menu' => 'test-menu',
				'position' => $position,
				'data' => json_encode($data),
			],
		)->run();
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

		$this->throws(RuntimeException::class, "Menu 'non-existent-menu' not found");
		new Menu($context, 'non-existent-menu');
	}

	public function testEmptyMenuIteratesNothingAndRendersNothing(): void
	{
		$this->db()->execute(
			"INSERT INTO cms.menus (menu, description) VALUES ('test-menu-empty', '{\"zxx\": \"Empty\"}')",
		)->run();

		$menu = new Menu($this->createContext(), 'test-menu-empty');

		$this->assertSame([], iterator_to_array($menu));
		$this->assertSame('', $menu->html('main-menu'));
	}

	public function testHiddenItemsTakeTheirSubtreeOutOfTheMenu(): void
	{
		$this->db()->execute(
			"UPDATE cms.menu_items SET hidden = true WHERE item = 'about'",
		)->run();
		$context = $this->createContext();

		$visible = new Menu($context, 'test-menu');
		$this->assertNotContains('about', array_keys(iterator_to_array($visible)));
		// The child rode along; nothing was orphaned to the root.
		$this->assertNotContains('about.team', array_keys(iterator_to_array($visible)));
		$this->assertStringNotContainsString('/about', $visible->html());

		$all = new Menu($context, 'test-menu', hidden: true);
		$items = iterator_to_array($all);
		$this->assertArrayHasKey('about', $items);
		$this->assertTrue($items['about']->hidden());
		$this->assertFalse($items['home']->hidden());
		$this->assertSame(
			['about.team'],
			array_map(
				static fn($child) => $child->id(),
				iterator_to_array($items['about']->children()),
			),
		);
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
		$this->assertEquals('node', $home->type());
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

		// Exactly one child: an item id containing a dot (as 'about.team'
		// here) used to be duplicated by the path-splitting tree builder.
		$children = iterator_to_array($about->children());
		$this->assertCount(1, $children);
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

	public function testWrapperKeepsTheCallerClass(): void
	{
		// The wrapper class used to be clobbered by the last item's class.
		$html = new Menu($this->createContext(), 'test-menu')->html('main-menu', 'nav');

		$this->assertStringContainsString('<nav class="main-menu">', $html);
		$this->assertStringNotContainsString('<nav class="contact-link">', $html);
	}

	public function testUrlItemRendersAnchorWithTargetAndRel(): void
	{
		$this->insertItem('external', [
			'type' => 'url',
			'title' => ['en' => 'Partner'],
			'path' => ['en' => 'https://example.com/partner'],
			'target' => '_blank',
		]);

		$html = new Menu($this->createContext(), 'test-menu')->html();

		$this->assertStringContainsString(
			'<a href="https://example.com/partner" target="_blank" rel="noopener">',
			$html,
		);
	}

	public function testAssetItemLinksTheFile(): void
	{
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, creator)
			VALUES ('menuasset1', 'local', 'me/menuasset1/prospekt.pdf', 'prospekt.pdf', 1)",
		)->run();
		$this->insertItem('brochure', [
			'type' => 'asset',
			'asset' => 'menuasset1',
			'title' => ['en' => 'Brochure'],
		]);

		$menu = new Menu($this->createContext(), 'test-menu');
		$items = iterator_to_array($menu);

		$this->assertStringEndsWith('me/menuasset1/prospekt.pdf', (string) $items['brochure']->href());
		$this->assertStringContainsString('me/menuasset1/prospekt.pdf', $menu->html());
	}

	public function testAssetItemWithoutResolvableAssetStaysUnlinked(): void
	{
		$this->insertItem('lost', [
			'type' => 'asset',
			'asset' => 'never-was',
			'title' => ['en' => 'Lost file'],
		]);

		$menu = new Menu($this->createContext(), 'test-menu');
		$items = iterator_to_array($menu);

		$this->assertNull($items['lost']->href());
		$this->assertStringContainsString('<span>Lost file</span>', $menu->html());
	}

	public function testUnknownTypeRendersUnlinkedLabel(): void
	{
		$this->insertItem('label', [
			'type' => 'heading',
			'title' => ['en' => 'Section'],
		]);

		$menu = new Menu($this->createContext(), 'test-menu');
		$items = iterator_to_array($menu);

		$this->assertNull($items['label']->href());
		$this->assertStringContainsString('<span>Section</span>', $menu->html());
	}

	public function testEscapesTitlesHrefsAndClasses(): void
	{
		$this->insertItem('sneaky', [
			'type' => 'url',
			'title' => ['en' => 'Evil <script>alert(1)</script>'],
			'path' => ['en' => '/x?a=1&b="2"'],
			'class' => '"><script>',
		]);

		$html = new Menu($this->createContext(), 'test-menu')->html();

		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringContainsString('Evil &lt;script&gt;', $html);
		$this->assertStringContainsString('href="/x?a=1&amp;b=&quot;2&quot;"', $html);
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

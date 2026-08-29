<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

/**
 * The menus area: the rail, menu create/edit/delete, and its permissions.
 * Superusers manage the set of menus; admins edit what is inside them.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelMenusTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->authenticateAs('superuser');
	}

	private function createMenu(string $handle, string $description): void
	{
		$this->db()->execute(
			'INSERT INTO cms.menus (menu, description) VALUES (:menu, :description)',
			['menu' => $handle, 'description' => $description],
		)->run();
	}

	private function createItem(string $menu, string $item, int $position = 1): void
	{
		$this->db()->execute(
			'INSERT INTO cms.menu_items (item, parent, menu, position, data)
			VALUES (:item, NULL, :menu, :position, :data::jsonb)',
			[
				'item' => $item,
				'menu' => $menu,
				'position' => $position,
				'data' => json_encode(['type' => 'url', 'title' => ['en' => $item], 'path' => ['en' => '/x']]),
			],
		)->run();
	}

	private function menuHandles(): array
	{
		return array_column(
			$this->db()->execute('SELECT menu FROM cms.menus ORDER BY menu')->all(),
			'menu',
		);
	}

	public function testTheMenusAreaAppearsForAdminsOnly(): void
	{
		$admin = $this->getHtmlResponse($this->makeRequest('GET', '/cp'));
		$this->assertMatchesRegularExpression(
			'/<a\s[^>]*class="area"[^>]*href="\/cp\/menus"[^>]*>\s*Menus\s*<\/a>/',
			$admin,
		);

		$this->authenticateAs('editor');
		$editor = $this->getHtmlResponse($this->makeRequest('GET', '/cp'));
		$this->assertStringNotContainsString('href="/cp/menus"', $editor);
	}

	public function testEditorsAreForbidden(): void
	{
		$this->authenticateAs('editor');

		$this->assertResponseStatus(403, $this->makeRequest('GET', '/cp/menus'));
		$this->assertResponseStatus(403, $this->makeRequest('POST', '/cp/menus/create', [
			'body' => ['menu' => 'sneaky', 'description' => 'Nope'],
		]));
	}

	public function testAdminsCannotAddOrRemoveMenus(): void
	{
		$this->createMenu('head', 'Header links');
		$this->authenticateAs('admin');

		$this->assertResponseStatus(403, $this->makeRequest('GET', '/cp/menus/create'));
		$this->assertResponseStatus(403, $this->makeRequest('POST', '/cp/menus/create', [
			'body' => ['menu' => 'extra', 'description' => 'Extra'],
		]));
		$this->assertResponseStatus(403, $this->makeRequest('POST', '/cp/menus/head/delete'));
		$this->assertSame(['head'], $this->menuHandles());
	}

	public function testAdminsSeeTheMenuWithoutItsLifecycleControls(): void
	{
		$this->createMenu('head', 'Header links');
		$this->authenticateAs('admin');

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/head'));

		// The handle is chrome, the description stays editable.
		$this->assertMatchesRegularExpression('/id="menu-handle"[^>]*disabled/s', $html);
		$this->assertStringContainsString('Only superusers can change the handle.', $html);
		$this->assertStringNotContainsString('/cp/menus/head/delete', $html);
		$this->assertStringNotContainsString('href="/cp/menus/create"', $html);
		$this->assertStringContainsString('id="menu-description"', $html);
	}

	public function testAdminsCannotRenameAMenuThroughTheBody(): void
	{
		$this->createMenu('head', 'Header links');
		$this->authenticateAs('admin');

		$response = $this->makeRequest('POST', '/cp/menus/head/edit', [
			'body' => ['menu' => 'renamed', 'description' => 'New description'],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp/menus/head?notice=updated', $response->getHeaderLine('Location'));
		// The description saved, the handle did not move.
		$this->assertSame(['head'], $this->menuHandles());
		$this->assertSame(
			'New description',
			$this->db()->execute(
				"SELECT description FROM cms.menus WHERE menu = 'head'",
			)->one()['description'],
		);
	}

	public function testTheAreaOpensTheFirstMenuByDescription(): void
	{
		$this->createMenu('aaa-handle', 'Second by description');
		$this->createMenu('zzz-handle', 'First by description');

		$response = $this->makeRequest('GET', '/cp/menus');

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp/menus/zzz-handle', $response->getHeaderLine('Location'));
	}

	public function testTheAreaCarriesANoticeIntoTheFirstMenu(): void
	{
		$this->createMenu('main-nav', 'Main navigation');

		$response = $this->makeRequest('GET', '/cp/menus?notice=deleted');

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp/menus/main-nav?notice=deleted', $response->getHeaderLine('Location'));
		$this->assertStringContainsString(
			'Menu deleted.',
			$this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/main-nav?notice=deleted')),
		);
	}

	public function testTheRailListsTheMenusAndMarksTheOpenOne(): void
	{
		$this->createMenu('main-nav', 'Main navigation');
		$this->createMenu('footer', 'Footer links');
		$this->createItem('main-nav', 'menu-e2e-one', 1);
		$this->createItem('main-nav', 'menu-e2e-two', 2);

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/main-nav'));

		$this->assertStringContainsString('id="menu-nav"', $html);
		$this->assertStringContainsString('href="/cp/menus/footer"', $html);
		$this->assertStringContainsString('href="/cp/menus/create"', $html);
		// The rail reads as menu names; hovering spells out the truncated ones
		// and names the handle behind them.
		$this->assertStringContainsString('<span>Main navigation</span>', $html);
		$this->assertStringContainsString('title="Main navigation · main-nav"', $html);
		// The open menu is marked, and its item count rides along as the badge.
		$this->assertMatchesRegularExpression(
			'/href="\/cp\/menus\/main-nav"[^>]*aria-current="page"/s',
			$html,
		);
		$this->assertStringContainsString('<span class="badge">2</span>', $html);
	}

	public function testAnEmptyAreaRendersTheEmptyStateWithoutARail(): void
	{
		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus'));

		$this->assertStringContainsString('No menus yet.', $html);
		$this->assertStringNotContainsString('id="menu-nav"', $html);
	}

	public function testTheCreateFormRenders(): void
	{
		$response = $this->makeRequest('GET', '/cp/menus/create');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('action="/cp/menus/create"', $html);
		// Nothing to delete yet.
		$this->assertStringNotContainsString('/delete"', $html);
	}

	public function testCreateStoresAndOpensTheNewMenu(): void
	{
		$response = $this->makeRequest('POST', '/cp/menus/create', [
			'body' => ['menu' => 'footer', 'description' => 'Footer links'],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp/menus/footer?notice=created', $response->getHeaderLine('Location'));
		$this->assertSame(['footer'], $this->menuHandles());

		$tree = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/footer?notice=created'));
		$this->assertStringContainsString('Menu created.', $tree);
	}

	public function testCreateRejectsInvalidReservedAndTakenHandles(): void
	{
		$this->createMenu('taken', 'Taken');

		$invalid = $this->makeRequest('POST', '/cp/menus/create', [
			'body' => ['menu' => 'Not A Handle', 'description' => 'X'],
		]);
		$this->assertResponseOk($invalid);
		$this->assertStringContainsString(
			'The handle needs 1–32 characters',
			$this->getHtmlResponse($invalid),
		);

		$reserved = $this->makeRequest('POST', '/cp/menus/create', [
			'body' => ['menu' => 'create', 'description' => 'X'],
		]);
		$this->assertStringContainsString(
			'This handle is reserved.',
			$this->getHtmlResponse($reserved),
		);

		$taken = $this->makeRequest('POST', '/cp/menus/create', [
			'body' => ['menu' => 'taken', 'description' => 'X'],
		]);
		$this->assertStringContainsString(
			'A menu with this handle already exists.',
			$this->getHtmlResponse($taken),
		);

		$this->assertSame(['taken'], $this->menuHandles());
	}

	public function testCreateRequiresADescription(): void
	{
		$response = $this->makeRequest('POST', '/cp/menus/create', [
			'body' => ['menu' => 'undescribed', 'description' => '  '],
		]);

		$this->assertResponseOk($response);
		$this->assertStringContainsString(
			'A description is required',
			$this->getHtmlResponse($response),
		);
		$this->assertSame([], $this->menuHandles());
	}

	public function testTheTreeScreenCarriesTheMenuFieldsAndItsDelete(): void
	{
		$this->createMenu('head', 'Header links');

		$response = $this->makeRequest('GET', '/cp/menus/head');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('action="/cp/menus/head/edit"', $html);
		$this->assertStringContainsString('value="head"', $html);
		$this->assertStringContainsString('value="Header links"', $html);
		$this->assertStringContainsString('Renaming the handle breaks templates', $html);
		// Delete sits in the same bar; there is no edit screen behind a button.
		$this->assertStringContainsString('action="/cp/menus/head/delete"', $html);
		$this->assertStringNotContainsString('disabled', $html);
	}

	public function testTheDeleteConfirmNamesTheItemCount(): void
	{
		$this->createMenu('stocked', 'Stocked');
		$this->createItem('stocked', 'menu-e2e-stocked-one', 1);
		$this->createItem('stocked', 'menu-e2e-stocked-two', 2);

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/menus/stocked'));

		$this->assertStringContainsString('It contains 2 items.', $html);
	}

	public function testTheEditScreenIsGone(): void
	{
		$this->createMenu('head', 'Header links');

		$this->assertResponseStatus(404, $this->makeRequest('GET', '/cp/menus/head/edit'));
	}

	public function testARejectedHandleComesBackOnTheTreeScreen(): void
	{
		$this->createMenu('head', 'Header links');
		$this->createMenu('taken', 'Taken');

		$response = $this->makeRequest('POST', '/cp/menus/head/edit', [
			'body' => ['menu' => 'taken', 'description' => 'Header links'],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('A menu with this handle already exists.', $html);
		// The tree is still on screen, and the field keeps what was typed.
		$this->assertStringContainsString('class="menu-props"', $html);
		$this->assertStringContainsString('value="taken"', $html);
		$this->assertSame(['head', 'taken'], $this->menuHandles());
	}

	public function testUpdateRenamesTheHandleAndItsItemsFollow(): void
	{
		$this->createMenu('old-name', 'Old');
		$this->createItem('old-name', 'menu-e2e-follow');

		$response = $this->makeRequest('POST', '/cp/menus/old-name/edit', [
			'body' => ['menu' => 'new-name', 'description' => 'New description'],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp/menus/new-name?notice=updated', $response->getHeaderLine('Location'));
		$this->assertSame(['new-name'], $this->menuHandles());
		$this->assertSame(
			'new-name',
			$this->db()->execute(
				"SELECT menu FROM cms.menu_items WHERE item = 'menu-e2e-follow'",
			)->one()['menu'],
		);
		$this->assertSame(
			'New description',
			$this->db()->execute(
				"SELECT description FROM cms.menus WHERE menu = 'new-name'",
			)->one()['description'],
		);
	}

	public function testDeleteRemovesTheMenuWithItsItems(): void
	{
		$this->createMenu('doomed', 'Doomed');
		$this->createItem('doomed', 'menu-e2e-doomed');

		$response = $this->makeRequest('POST', '/cp/menus/doomed/delete');

		$this->assertResponseStatus(303, $response);
		$this->assertSame('/cp/menus?notice=deleted', $response->getHeaderLine('Location'));
		$this->assertSame([], $this->menuHandles());
		$this->assertNull(
			$this->db()->execute(
				"SELECT true AS t FROM cms.menu_items WHERE menu = 'doomed'",
			)->first(),
		);
	}

	public function testUnknownMenuAnswers404(): void
	{
		$this->assertResponseStatus(404, $this->makeRequest('GET', '/cp/menus/ghost'));
		$this->assertResponseStatus(404, $this->makeRequest('POST', '/cp/menus/ghost/delete'));
	}
}

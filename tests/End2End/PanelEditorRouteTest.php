<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Block as Builtin;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Blocks;
use Cosray\Field\Textarea;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Node\TestConditionalDocument;
use Cosray\Tests\Fixtures\Node\TestEmbeddedDocument;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\Fixtures\Node\TestNodeWithEntries;

final class PanelEditorRouteTest extends End2EndTestCase
{
	private ?int $articleTypeId = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->section('Inhalt')->collection(TestArticlesCollection::class);
		$plugin->node(TestConditionalDocument::class);
		$plugin->node(TestEmbeddedDocument::class);
		$plugin->node(TestNodeWithEntries::class);

		return $plugin;
	}

	public function testEntriesRenderAsServerRenderedTypedRepeater(): void
	{
		$this->authenticateAs('editor');
		$entriesType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-node-with-entries'",
		)->first();
		$typeId = $entriesType
			? (int) $entriesType['type']
			: $this->createTestType('test-node-with-entries');
		$this->createTestNode([
			'uid' => 'panel-editor-entries',
			'type' => $typeId,
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['zxx' => 'With entries']],
				'entries' => [
					'type' => \Cosray\Field\Entries::class,
					'value' => [
						'zxx' => [
							[
								'uid' => 'entry-a',
								'type' => TestEntry::class,
								'fields' => [
									'title' => [
										'type' => \Cosray\Field\Text::class,
										'value' => ['en' => 'First entry'],
									],
								],
							],
						],
					],
				],
			],
		]);

		$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-entries');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);

		$base = 'content[entries][value][zxx]';
		$this->assertStringContainsString('class="cms-entries"', $html);
		$this->assertStringContainsString('data-name="' . $base . '"', $html);
		$this->assertStringContainsString('name="' . $base . '[0][uid]"', $html);
		$this->assertStringContainsString('value="entry-a"', $html);
		// Sub-fields render through the regular wrapper at the row's root.
		$this->assertStringContainsString('name="' . $base . '[0][fields][title][value][en]"', $html);
		// Element controls inside rows go through the form host at a deep name.
		$this->assertStringContainsString('name="' . $base . '[0][fields][content][json]"', $html);
		// The server renders the entry title from the first text value.
		$this->assertStringContainsString('First entry', $html);
		// One inert template per allowed type with the stamp placeholder.
		$this->assertStringContainsString(
			'data-repeater-template="' . TestEntry::class . '"',
			$html,
		);
		$this->assertStringContainsString('name="' . $base . '[__i__][uid]"', $html);
		$this->assertStringContainsString('data-repeater-add="' . TestEntry::class . '"', $html);
	}

	public function testBlocksRenderAsServerRenderedTypedRepeaterWithAGrid(): void
	{
		$this->authenticateAs('editor');
		$mediaType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-media-document'",
		)->first();
		$typeId = $mediaType ? (int) $mediaType['type'] : $this->createTestType('test-media-document');
		// Encoded up front: the helper's legacy normalizer would rewrite typed rows.
		$this->createTestNode([
			'uid' => 'panel-editor-blocks',
			'type' => $typeId,
			'published' => true,
			'content' => json_encode([
				'contentBlocks' => [
					'type' => Blocks::class,
					'value' => [
						'en' => [
							[
								'uid' => 'block-a',
								'type' => Builtin\Text::class,
								'layout' => ['span' => 6, 'rows' => 2, 'indent' => 3],
								'fields' => [
									'text' => ['type' => Textarea::class, 'value' => ['zxx' => 'First block']],
								],
								'meta' => ['class' => ['zxx' => 'wide']],
							],
							[
								'uid' => 'block-gone',
								'type' => 'Acme\\Gone',
								'layout' => ['span' => 12, 'rows' => 1, 'indent' => 0],
								'fields' => [],
							],
						],
						'de' => [],
					],
				],
			]),
		]);

		$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-blocks');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);

		// Asymmetric: one list per locale under the field-level tabs.
		$en = 'content[contentBlocks][value][en]';
		$this->assertStringContainsString('data-locale-tab="de"', $html);
		$this->assertStringContainsString('data-name="' . $en . '"', $html);
		$this->assertStringContainsString('data-name="content[contentBlocks][value][de]"', $html);
		$this->assertStringContainsString(
			'class="cms-blocks-editor is-grid" data-repeater data-name="'
				. $en
				. '" data-id="field-contentBlocks-en" data-columns="12" data-min="2" style="--columns: 12"',
			preg_replace('/\s+/', ' ', $html) ?? '',
		);
		// The row: uid, type and layout as hidden inputs, the layout on the
		// row as custom properties, the block meta in the row's own dialog.
		$this->assertStringContainsString('name="' . $en . '[0][uid]"', $html);
		$this->assertStringContainsString('value="block-a"', $html);
		$this->assertStringContainsString('value="' . Builtin\Text::class . '"', $html);
		$this->assertStringContainsString('name="' . $en . '[0][layout][span]"', $html);
		$this->assertStringContainsString('data-layout="indent"', $html);
		$this->assertStringContainsString('style="--span: 6; --rows: 2; --indent: 3; --reserved: 9"', $html);
		$this->assertStringContainsString('name="' . $en . '[0][fields][text][value][zxx]"', $html);
		$this->assertStringContainsString('First block', $html);
		$this->assertStringContainsString('name="' . $en . '[0][meta][class][zxx]"', $html);
		$this->assertStringContainsString('value="wide"', $html);
		$this->assertStringContainsString('name="' . $en . '[0][meta][id][zxx]"', $html);
		// A block with one field hides that field's label; where the field
		// carries a meta dialog of its own, the row stays for its button.
		$this->assertHtmlNodeExists(
			'//template[@data-repeater-template="Cosray\\Block\\Text"]'
				. '//div[contains(@class, "cms-field")]/label[contains(@class, "sr-only")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//template[@data-repeater-template="Cosray\\Block\\Youtube"]'
				. '//div[contains(@class, "cms-field")]'
				. '/label[not(contains(@class, "sr-only"))]'
				. '[div[contains(@class, "sr-only")]][.//button[@data-meta-open]]',
			$html,
		);
		// A row of a type no longer offered renders without inputs.
		$this->assertStringContainsString('Unknown block type: Acme\Gone', $html);
		$this->assertStringNotContainsString('value="block-gone"', $html);
		// Templates per offered type, the picker in the footer and one
		// inserter per row, before it; the row menu inserts nothing.
		$this->assertStringContainsString('data-repeater-template="' . Builtin\Heading::class . '"', $html);
		$this->assertStringContainsString('name="' . $en . '[__i__][layout][span]"', $html);
		$this->assertStringContainsString(
			'data-repeater-add="' . Builtin\RichText::class . '" data-repeater-insert="append"',
			preg_replace('/\s+/', ' ', $html) ?? '',
		);
		$this->assertHtmlNodeExists(
			'//div[@data-repeater-row]/details[contains(@class, "inserter")]'
				. '/div/button[@data-repeater-insert="before"]',
			$html,
		);
		$this->assertHtmlNodeMissing('//div[@data-repeater-row]//*[@data-repeater-insert="after"]', $html);
		$this->assertHtmlNodeMissing('//div[@class="kebab-menu"]//*[@data-repeater-insert]', $html);
		$this->assertHtmlNodeExists('//div[@class="kebab-menu"]/button[@data-repeater-duplicate]', $html);
		$this->assertHtmlNodeExists('//span[@data-repeater-grip][@tabindex="0"][@aria-keyshortcuts]', $html);
		// The layout numbers in the settings dialog, each capped by the room
		// the others leave: span 6 at indent 3 in twelve columns.
		$this->assertStringContainsString(
			'data-layout-input="span" value="6" min="2" max="9"',
			preg_replace('/\s+/', ' ', $html) ?? '',
		);
		$this->assertStringContainsString(
			'data-layout-input="indent" value="3" min="0" max="6"',
			preg_replace('/\s+/', ' ', $html) ?? '',
		);
		$this->assertStringNotContainsString('data-layout-step', $html);
	}

	public function testPanelEditorRouteRendersShellForAuthenticatedUsers(): void
	{
		$this->authenticateAs('editor');
		$this->createArticle('panel-editor-a', 'Panel Editor A');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-a', [
			'query' => [
				'q' => 'Panel Editor',
				'offset' => '20',
				'limit' => '10',
				'sort' => 'uid',
				'dir' => 'asc',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<!DOCTYPE html>', $html);
		$this->assertStringContainsString(
			'<style>@layer tokens, reset, panel, plugin, theme;</style>',
			$html,
		);
		$this->assertStringContainsString('class="page cms-node"', $html);
		$this->assertStringNotContainsString('Back to list', $html);
		$this->assertStringNotContainsString('topbar-editor', $html);
		$this->assertPanelStaticStateIsRendered($html);
		$this->assertEditorAssetStateIsRendered($html);
	}

	public function testElementControlsRenderThroughTheFormHost(): void
	{
		$this->authenticateAs('editor');
		$mediaType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-media-document'",
		)->first();
		$mediaTypeId = $mediaType
			? (int) $mediaType['type']
			: $this->createTestType('test-media-document');
		$this->createTestNode([
			'uid' => 'panel-editor-media',
			'type' => $mediaTypeId,
			'published' => true,
			'content' => [],
		]);

		$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-media');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<cosray-host', $html);
		$this->assertStringContainsString('name="content[gallery][json]"', $html);
		$this->assertStringContainsString('tag="cosray-image"', $html);
		$this->assertStringContainsString('module="cosray:media"', $html);
		// An empty blocks field renders its editor with templates and picker.
		$this->assertStringContainsString('class="cms-blocks-editor is-grid"', $html);
		$this->assertStringContainsString('0 blocks', $html);
		$this->assertStringContainsString('node="panel-editor-media"', $html);
		$this->assertStringContainsString('id="cosray-system-data"', $html);
		$this->assertStringContainsString('"allowedFiles"', $html);
	}

	public function testSidebarKeepsTheCollectionCurrentWhileANodeIsOpen(): void
	{
		$this->authenticateAs('editor');
		$this->createArticle('panel-editor-current', 'Panel Editor Current');

		$collection = $this->navLink(
			$this->getHtmlResponse(
				$this->makeRequest('GET', '/cp/collection/test-articles'),
			),
			'/cp/collection/test-articles',
		);
		$this->assertStringContainsString('aria-current="page"', $collection);

		$node = $this->getHtmlResponse(
			$this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-current'),
		);
		$link = $this->navLink($node, '/cp/collection/test-articles');

		// The node lives below the collection URL, so the entry stays marked.
		$this->assertStringContainsString('aria-current="page"', $link);
		$this->assertStringNotContainsString(
			'aria-current="page"',
			$this->navLink($node, '/cp', 'area'),
		);

		// The editor belongs to content, so the masthead says so while the
		// node is open — the rail entry alone is not the whole answer.
		$this->assertStringContainsString(
			'aria-current="page"',
			$this->navLink($node, '/cp/collection/test-articles', 'area'),
		);
	}

	public function testConditionalFieldsCarryTheirConditionIntoTheMarkup(): void
	{
		$this->authenticateAs('editor');
		$conditionalType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-conditional-document'",
		)->first();
		$typeId = $conditionalType
			? (int) $conditionalType['type']
			: $this->createTestType('test-conditional-document');
		$this->createTestNode([
			'uid' => 'panel-editor-when',
			'type' => $typeId,
			'published' => true,
			'content' => [
				'multiDay' => ['type' => 'checkbox', 'value' => ['zxx' => false]],
			],
		]);

		$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-when');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString(
			'data-when=\'{"field":"multiDay","op":"truthy","value":null}\'',
			$html,
		);
		$this->assertStringContainsString(
			'data-when=\'{"field":"title","op":"eq","value":"hero"}\'',
			$html,
		);

		// Date/time controls render through the shared input view without
		// extra attrs — the path the attrs regression hid in.
		$this->assertStringContainsString('name="content[eventDate][value][zxx]"', $html);
		$this->assertStringContainsString('type="date"', $html);
		$this->assertStringContainsString('name="content[eventTime][value][zxx]"', $html);
		$this->assertStringContainsString('type="time"', $html);

		// The styled fixture field exposes meta editing through a dialog.
		$this->assertStringContainsString('data-meta-open', $html);
		$this->assertStringContainsString('<dialog class="cms-meta" data-meta>', $html);
		$this->assertStringContainsString('name="content[styled][meta][cssClass][zxx]"', $html);
		$this->assertStringContainsString('name="content[styled][meta][tone][zxx]"', $html);
	}

	public function testEmbeddedFieldsRenderInsideConfiguredFieldset(): void
	{
		$this->authenticateAs('editor');
		$embeddedType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-embedded-document'",
		)->first();
		$typeId = $embeddedType
			? (int) $embeddedType['type']
			: $this->createTestType('test-embedded-document');
		$this->createTestNode([
			'uid' => 'panel-editor-fieldset',
			'type' => $typeId,
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Embedded title']],
				'body' => ['type' => 'text', 'value' => ['zxx' => 'Embedded body']],
			],
		]);

		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-articles/panel-editor-fieldset',
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="cms-fieldset"', $html);
		$this->assertStringContainsString('data-fieldset="baseFields"', $html);
		$this->assertStringContainsString('Document fields</legend>', $html);
		$this->assertStringContainsString('Reusable document fields', $html);
		$this->assertStringContainsString('name="content[title][value][en]"', $html);
		$this->assertStringContainsString('name="content[body][value][zxx]"', $html);
		$this->assertStringNotContainsString('content[baseFields]', $html);
	}

	public function testSettingsPaneScopesPathPreviewToRouteInputs(): void
	{
		$this->authenticateAs('editor');
		$uid = 'panel-editor-page';
		$this->createTestNode([
			'uid' => $uid,
			'type' => $this->pageTypeId(),
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'A Page']],
			],
		]);
		$response = $this->makeRequest('GET', '/cp/collection/test-articles/' . $uid);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);

		// Handle and path inputs carry the js-path-source hook so the preview
		// only recomputes when a route-determining input changes.
		$this->assertStringContainsString('id="cms-node-handle"', $html);
		$this->assertStringContainsString('class="js-path-source"', $html);
		$this->assertStringContainsString('name="paths[en]"', $html);

		// The preview trigger is scoped to those inputs, not the whole form,
		// so editing content fields no longer fires the paths POST.
		$this->assertStringContainsString('id="generated-paths"', $html);
		$this->assertStringContainsString('from:.js-path-source', $html);
		$this->assertStringNotContainsString('from:#node-editor-form', $html);

		// The initial preview renders server-side (route is /test/{uid}).
		$this->assertStringContainsString('cms-generated-path', $html);
		$this->assertStringContainsString('/test/' . $uid, $html);

		// The /test/{uid} route references no content field, so no field
		// wrapper is marked (only the handle input carries js-path-source).
		$this->assertStringNotContainsString('<div class="js-path-source"', $html);

		// The inspector lists the node's fact rows for an existing node.
		$this->assertStringContainsString('class="facts"', $html);
		$this->assertStringContainsString('<dt>Created</dt>', $html);
		$this->assertStringContainsString('<code>' . $uid . '</code>', $html);
	}

	public function testRouteReferencedContentFieldsAreMarkedAsPathSources(): void
	{
		$this->authenticateAs('editor');
		$uid = 'panel-editor-titled';
		$this->createTestNode([
			'uid' => $uid,
			'type' => $this->createTestType('parent-path-route-page'),
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Titled Page']],
			],
		]);
		$response = $this->makeRequest('GET', '/cp/collection/test-articles/' . $uid);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);

		// The route /{parent}/{title} references the title content field, so
		// its wrapper is marked js-path-source and edits to it live-refresh
		// the preview; the referenced input stays inside that wrapper.
		$this->assertHtmlNodeExists(
			'//div[contains(concat(" ", normalize-space(@class), " "), " js-path-source ")]//input[starts-with(@name, "content[title][value]")]',
			$html,
		);
	}

	public function testBoostedPanelEditorRouteRendersPartial(): void
	{
		$this->authenticateAs('editor');
		$this->createArticle('panel-editor-boosted', 'Panel Editor Boosted');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-boosted', [
			'headers' => [
				'HX-Request' => 'true',
				'HX-Boosted' => 'true',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="page cms-node"', $html);
		$this->assertStringNotContainsString('<!DOCTYPE html>', $html);
		$this->assertStringNotContainsString('class="panel"', $html);
	}

	public function testCollectionRowsLinkToPanelEditorRoute(): void
	{
		$this->authenticateAs('editor');
		$this->createArticle('panel-editor-link', 'Panel Editor Link');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'q' => 'Panel Editor',
				'sort' => 'uid',
				'dir' => 'asc',
				'limit' => '10',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString(
			'href="/cp/collection/test-articles/panel-editor-link?q=Panel%20Editor&amp;sort=uid&amp;dir=asc&amp;limit=10"',
			$html,
		);
		$this->assertStringContainsString('class="value link"', $html);
	}

	public function testPanelEditorRouteUsesViteDevServerWhenPanelDevIsEnabled(): void
	{
		$_SERVER['COSRAY_PANEL_DEV'] = 'true';
		$_SERVER['COSRAY_PANEL_DEV_ORIGIN'] = 'http://localhost:2001';

		try {
			$this->authenticateAs('editor');
			$this->createArticle('panel-editor-dev', 'Panel Editor Dev');

			$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-dev');

			$this->assertResponseOk($response);
			$html = $this->getHtmlResponse($response);
			$this->assertStringContainsString(
				'src="http://localhost:2001/node_modules/htmx.org/dist/htmx.min.js"',
				$html,
			);
			$this->assertStringContainsString('src="http://localhost:2001/@vite/client"', $html);
			$this->assertStringContainsString('src="http://localhost:2001/src/panel.ts"', $html);
			$this->assertStringNotContainsString('/cp/static/panel.js', $html);
			$this->assertStringNotContainsString('/cp/assets/editor/node-editor.js', $html);
			$this->assertStringNotContainsString('/cp/assets/editor/node-editor.css', $html);
		} finally {
			unset($_SERVER['COSRAY_PANEL_DEV'], $_SERVER['COSRAY_PANEL_DEV_ORIGIN']);
		}
	}

	public function testPanelEditorRouteReturnsNotFoundForUnknownCollection(): void
	{
		$this->authenticateAs('editor');

		$response = $this->makeRequest('GET', '/cp/collection/does-not-exist/panel-editor-a');

		$this->assertResponseStatus(404, $response);
	}

	public function testPanelEditorRouteRedirectsGuestToLogin(): void
	{
		$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-a');

		$this->assertResponseStatus(303, $response);
		$this->assertSame(
			'/cp/login?next=%2Fcp%2Fcollection%2Ftest-articles%2Fpanel-editor-a',
			$response->getHeaderLine('Location'),
		);
	}

	private function assertEditorAssetStateIsRendered(string $html): void
	{
		// The editor is a server-rendered form regardless of the panel static assets.
		// The form wraps both panes, so the inspector's settings submit with the
		// content fields.
		$this->assertStringContainsString('id="node-editor-form"', $html);
		$this->assertStringContainsString('class="panes"', $html);
		$this->assertStringContainsString(
			'action="/cp/collection/test-articles/panel-editor-a?q=Panel%20Editor&amp;sort=uid&amp;dir=asc&amp;offset=20&amp;limit=10"',
			$html,
		);
		$this->assertStringContainsString('name="content[title][value][en]"', $html);
		$this->assertStringContainsString('value="Panel Editor A"', $html);
		$this->assertStringContainsString('name="content[content][value][en]"', $html);
		$this->assertStringContainsString('data-locale="de"', $html);
		// Sub-route actions must not inherit the editor query string.
		$this->assertStringContainsString(
			'action="/cp/collection/test-articles/panel-editor-a/delete"',
			$html,
		);
		// Native validation cannot handle legitimately hidden controls
		// (locale variants, panes) — the server validates.
		$this->assertHtmlNodeExists('//form[@id="node-editor-form" and @novalidate]', $html);
		// The title lives in the header beside the status pill, outside the
		// scrolling panes.
		$this->assertStringContainsString('<div class="line">', $html);
		$this->assertStringNotContainsString('id="cosray-node-editor"', $html);
		$this->assertStringNotContainsString('cosray-node-editor-data', $html);
		$this->assertStringNotContainsString('Panel bundle missing', $html);
	}

	private function assertPanelStaticStateIsRendered(string $html): void
	{
		if (!$this->hasPanelStatic()) {
			return;
		}

		$this->assertStringContainsString('href="/cp/static/panel.css"', $html);
		$this->assertStringContainsString('src="/cp/static/htmx.js"', $html);
		$this->assertStringContainsString('src="/cp/static/panel.js"', $html);
	}

	private function hasPanelStatic(): bool
	{
		return (
			is_file(dirname(__DIR__, 2) . '/public/cp/static/panel.js')
				&& is_file(dirname(__DIR__, 2) . '/public/cp/static/panel.css')
				&& is_file(dirname(__DIR__, 2) . '/public/cp/static/htmx.js')
		);
	}

	/**
	 * The opening tag of the navigation link pointing at `$href`. The class
	 * picks the region: the masthead's content area and the rail entry for the
	 * first collection share an href, so the href alone is ambiguous.
	 */
	private function navLink(string $html, string $href, string $class = 'link'): string
	{
		$found = preg_match(
			'/<a\s[^>]*class="'
				. preg_quote($class, '/')
				. '"[^>]*href="'
				. preg_quote($href, '/')
				. '"[^>]*>/',
			$html,
			$matches,
		);
		$this->assertSame(1, $found, "No {$class} navigation link for {$href}");

		return $matches[0];
	}

	private function createArticle(
		string $uid,
		string $title,
		string $changed = 'now()',
	): void {
		$this->createTestNode([
			'uid' => $uid,
			'type' => $this->articleTypeId(),
			'changed' => $changed,
			'published' => true,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => ['en' => $title],
				],
			],
		]);
	}

	private function articleTypeId(): int
	{
		if ($this->articleTypeId !== null) {
			return $this->articleTypeId;
		}

		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-article'",
		)->one();
		$this->assertNotEmpty($type);
		$this->articleTypeId = (int) $type['type'];

		return $this->articleTypeId;
	}

	private function pageTypeId(): int
	{
		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one();
		$this->assertNotEmpty($type);

		return (int) $type['type'];
	}
}

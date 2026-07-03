<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Node\TestConditionalDocument;

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

		return $plugin;
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
		$this->assertStringContainsString('id="main" class="page node"', $html);
		$this->assertStringNotContainsString('Back to list', $html);
		$this->assertStringNotContainsString('topbar-editor', $html);
		$this->assertPanelBuildStateIsRendered($html);
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
		$this->assertStringContainsString('tag="cosray-blocks"', $html);
		$this->assertStringContainsString('node="panel-editor-media"', $html);
		// The embedded system payload replaces the legacy /panel/boot call.
		$this->assertStringContainsString('id="cosray-system-data"', $html);
		$this->assertStringContainsString('"allowedFiles"', $html);
		$this->assertStringNotContainsString('/panel/boot', $html);
		$this->assertStringNotContainsString('/panel/api', $html);
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

		// The styled fixture field exposes meta editing through a dialog.
		$this->assertStringContainsString('data-meta-open', $html);
		$this->assertStringContainsString('<dialog class="cms-meta-dialog" data-meta>', $html);
		$this->assertStringContainsString('name="content[styled][meta][cssClass][zxx]"', $html);
		$this->assertStringContainsString('name="content[styled][meta][tone][zxx]"', $html);
	}

	public function testNodeApiPayloadCarriesControlDescriptors(): void
	{
		$this->authenticateAs('editor');
		$this->createArticle('panel-editor-api', 'Panel Editor Api');
		$response = $this->makeRequest('GET', '/panel/api/node/panel-editor-api');

		$this->assertResponseOk($response);
		$payload = json_decode((string) $response->getBody(), true);
		$fields = array_column($payload['fields'], null, 'name');

		// Field payloads carry the control descriptor the generic renderer dispatches on.
		$this->assertSame('text', $fields['title']['control']['name']);
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
		$this->assertStringContainsString('id="main" class="page node"', $html);
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
		$this->assertStringContainsString('class="collection-value collection-edit-link"', $html);
	}

	public function testPanelEditorRouteUsesViteDevServerInDevelopment(): void
	{
		$this->app = $this->createApp(['app.env' => 'development']);
		$this->authenticateAs('editor');
		$this->createArticle('panel-editor-dev', 'Panel Editor Dev');

		$response = $this->makeRequest('GET', '/cp/collection/test-articles/panel-editor-dev');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('src="http://localhost:2001/@vite/client"', $html);
		$this->assertStringContainsString('src="http://localhost:2001/src/panel.ts"', $html);
		$this->assertStringNotContainsString('/cp/build/panel.js', $html);
		$this->assertStringNotContainsString('/cp/assets/editor/node-editor.js', $html);
		$this->assertStringNotContainsString('/cp/assets/editor/node-editor.css', $html);
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
		// The editor is a server-rendered form regardless of the panel build.
		$this->assertStringContainsString('class="cms-node-form"', $html);
		$this->assertStringContainsString(
			'action="/cp/collection/test-articles/panel-editor-a?q=Panel%20Editor&amp;sort=uid&amp;dir=asc&amp;offset=20&amp;limit=10"',
			$html,
		);
		$this->assertStringContainsString('name="content[title][value][en]"', $html);
		$this->assertStringContainsString('value="Panel Editor A"', $html);
		$this->assertStringContainsString('name="content[content][value][en]"', $html);
		$this->assertStringContainsString('data-locale="de"', $html);
		$this->assertStringContainsString('cms-headline-title', $html);
		$this->assertStringNotContainsString('id="cosray-node-editor"', $html);
		$this->assertStringNotContainsString('cosray-node-editor-data', $html);
		$this->assertStringNotContainsString('Panel bundle missing', $html);
	}

	private function assertPanelBuildStateIsRendered(string $html): void
	{
		if (!$this->hasPanelBuild()) {
			return;
		}

		$this->assertStringContainsString('href="/cp/build/panel.css"', $html);
		$this->assertStringContainsString('src="/cp/build/panel.js"', $html);
	}

	private function hasPanelBuild(): bool
	{
		return (
			is_file(dirname(__DIR__, 2) . '/public/cp/build/panel.js')
			&& is_file(dirname(__DIR__, 2) . '/public/cp/build/panel.css')
		);
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
}

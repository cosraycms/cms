<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Config;
use Cosray\Plugin;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

final class PanelEditorRouteTest extends End2EndTestCase
{
	private ?int $articleTypeId = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
	}

	protected function createPlugin(Config $config): Plugin
	{
		$plugin = parent::createPlugin($config);
		$plugin->section('Inhalt')->collection(TestArticlesCollection::class);

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
		$this->assertStringNotContainsString('/cp/assets/build/panel.js', $html);
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
		if ($this->hasPanelBuild()) {
			$this->assertStringContainsString('id="cosray-node-editor"', $html);
			$this->assertStringContainsString('"node":"panel-editor-a"', $html);
			$this->assertStringContainsString('"name":"Test articles"', $html);
			$this->assertStringContainsString('"slug":"test-articles"', $html);
			$this->assertStringContainsString('"q":"Panel Editor"', $html);
			$this->assertStringContainsString('"offset":20', $html);
			$this->assertStringContainsString('"limit":10', $html);
			$this->assertStringContainsString('"sort":"uid"', $html);
			$this->assertStringContainsString('"dir":"asc"', $html);
			$this->assertStringNotContainsString('/cp/assets/editor/node-editor.js', $html);

			return;
		}

		$this->assertStringContainsString('Panel bundle missing', $html);
		$this->assertStringContainsString('cd panel &amp;&amp; pnpm run build', $html);
		$this->assertStringContainsString(
			'href="/panel/collection/test-articles/panel-editor-a?q=Panel%20Editor&amp;sort=uid&amp;dir=asc&amp;offset=20&amp;limit=10"',
			$html,
		);
	}

	private function assertPanelBuildStateIsRendered(string $html): void
	{
		if (!$this->hasPanelBuild()) {
			return;
		}

		$this->assertStringContainsString('href="/cp/assets/build/panel.css"', $html);
		$this->assertStringContainsString('src="/cp/assets/build/panel.js"', $html);
	}

	private function hasPanelBuild(): bool
	{
		return is_file(dirname(__DIR__, 2) . '/panel/build/panel.js');
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

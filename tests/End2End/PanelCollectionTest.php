<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Config;
use Cosray\Plugin;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

final class PanelCollectionTest extends End2EndTestCase
{
	private ?int $articleTypeId = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	protected function createPlugin(Config $config): Plugin
	{
		$plugin = parent::createPlugin($config);
		$plugin->section('Inhalt')->collection(TestArticlesCollection::class);

		return $plugin;
	}

	public function testPanelCollectionRouteRendersGridListWithoutTable(): void
	{
		$this->createArticle('panel-grid-a', 'Panel Grid A');
		$this->createArticle('panel-grid-b', 'Panel Grid B');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="collection-title">Test articles', $html);
		$this->assertStringContainsString('href="/cp/assets/styles/collection.css', $html);
		$this->assertStringContainsString('Panel Grid A', $html);
		$this->assertStringContainsString('Panel Grid B', $html);
		$this->assertStringContainsString('href="/cp/node/panel-grid-a"', $html);
		$this->assertStringContainsString('class="collection-list"', $html);
		$this->assertStringContainsString('class="collection-grid"', $html);
		$this->assertStringNotContainsString('<table', $html);
	}

	public function testBoostedCollectionRequestRendersPartialWithoutLayoutShell(): void
	{
		$this->createArticle('panel-grid-boosted', 'Panel Grid Boosted');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'headers' => [
				'HX-Request' => 'true',
				'HX-Boosted' => 'true',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="collection-page"', $html);
		$this->assertStringNotContainsString('<!DOCTYPE html>', $html);
		$this->assertStringNotContainsString('class="app"', $html);
	}

	public function testPanelCollectionRouteReturnsNotFoundForUnknownCollection(): void
	{
		$response = $this->makeRequest('GET', '/cp/collection/does-not-exist');

		$this->assertResponseStatus(404, $response);
	}

	private function createArticle(string $uid, string $title): void
	{
		$this->createTestNode([
			'uid' => $uid,
			'type' => $this->articleTypeId(),
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

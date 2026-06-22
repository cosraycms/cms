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
		$this->assertStringContainsString('href="/cp/assets/styles/collection.css"', $html);
		$this->assertStringContainsString('Panel Grid A', $html);
		$this->assertStringContainsString('Panel Grid B', $html);
		$this->assertStringContainsString('class="collection-list"', $html);
		$this->assertStringContainsString('class="collection-grid"', $html);
		$this->assertStringNotContainsString('<table', $html);
	}

	public function testPanelCollectionSearchFiltersRows(): void
	{
		$this->createArticle('panel-search-needle', 'Panel Search Needle');
		$this->createArticle('panel-search-haystack', 'Panel Search Haystack');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'q' => 'needle',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Panel Search Needle', $html);
		$this->assertStringNotContainsString('Panel Search Haystack', $html);
	}

	public function testPanelCollectionPaginatesRows(): void
	{
		$changed = '2026-01-01 10:00:00+00';
		$this->createArticle('panel-page-a', 'Panel Page A', $changed);
		$this->createArticle('panel-page-b', 'Panel Page B', $changed);
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'q' => 'Panel Page',
				'limit' => '1',
				'offset' => '1',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringNotContainsString('Panel Page A', $html);
		$this->assertStringContainsString('Panel Page B', $html);
	}

	public function testPanelCollectionRejectsInvalidSort(): void
	{
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'sort' => 'nope',
			],
		]);

		$this->assertResponseStatus(400, $response);
	}

	public function testPanelCollectionRejectsInvalidDirection(): void
	{
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'dir' => 'sideways',
			],
		]);

		$this->assertResponseStatus(400, $response);
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

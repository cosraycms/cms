<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Core\App;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Collection\TestMixedCollection;
use Cosray\Tests\Fixtures\Collection\TestServiceCollection;
use Cosray\Tests\Fixtures\Collection\TestSortedCollection;
use Cosray\Tests\Fixtures\Node\TestSortableEntry;

final class PanelCollectionTest extends End2EndTestCase
{
	private ?int $articleTypeId = null;

	protected function createApp(array $settings = []): App
	{
		return parent::createApp(array_merge([
			'app.timezone' => 'Europe/Berlin',
		], $settings));
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->section('Inhalt')->collection(TestArticlesCollection::class);
		$plugin->section('Inhalt')->collection(TestMixedCollection::class);
		$plugin->node(TestSortableEntry::class);
		$plugin->collection(TestSortedCollection::class);
		$plugin->collection(TestServiceCollection::class);

		return $plugin;
	}

	public function testPanelCollectionRouteRendersTableList(): void
	{
		$this->createArticle('panel-grid-a', 'Panel Grid A');
		$this->createArticle('panel-grid-b', 'Panel Grid B');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="page cms-collection"', $html);
		$this->assertStringContainsString('<h1>Test articles</h1>', $html);
		$this->assertStringNotContainsString('href="/cp/assets/styles/collection.css"', $html);
		$this->assertStringContainsString('Panel Grid A', $html);
		$this->assertStringContainsString('Panel Grid B', $html);
		$this->assertStringContainsString('class="cms-list" role="table"', $html);
		$this->assertStringContainsString(
			'<th class="col-status" role="columnheader">Status</th>',
			$html,
		);
		$this->assertStringNotContainsString('class="collection-grid"', $html);
	}

	public function testCollectionConstructorIsAutowired(): void
	{
		$this->createArticle('service-entry', 'Service Entry');
		$response = $this->makeRequest('GET', '/cp/collection/test-service');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Listed in Europe/Berlin', $html);
		$this->assertStringContainsString('Service Entry', $html);
	}

	public function testDefaultAndSelectedOrdersUseTheirOwnDirections(): void
	{
		$this->createArticle('sort-a', 'Zulu', '2026-02-01 00:00:00+00');
		$this->createArticle('sort-z', 'Alpha', '2026-01-01 00:00:00+00');
		foreach ([
			[[], 'sort-z'],
			[['sort' => 'title'], 'sort-z'],
			[['dir' => 'desc'], 'sort-a'],
			[['sort' => 'changed'], 'sort-a'],
			[['sort' => 'changed', 'dir' => 'asc'], 'sort-z'],
		] as [$query, $first]) {
			$response = $this->makeRequest('GET', '/cp/collection/test-articles', ['query' => $query]);
			$this->assertResponseOk($response);
			$html = $this->getHtmlResponse($response);
			$this->assertHtmlNodeExists('//tbody/tr[1][@data-uid="' . $first . '"]', $html);
			$this->assertHtmlNodeExists('//th[@aria-sort]/a', $html);
		}
	}

	public function testCustomCompoundAndTypedColumnSorts(): void
	{
		$type = $this->createTestType('test-sortable-entry');
		foreach ([
			'a' => ['Smith', 'Zoe', 2, '2026-01-01T10:00:00Z'],
			'b' => ['Smith', 'Amy', 10, '2026-02-01T10:00:00Z'],
			'c' => ['Brown', 'Zoe', -5, null],
		] as $uid => [$last, $first, $amount, $start]) {
			$this->createTestNode([
				'uid' => $uid,
				'type' => $type,
				'content' => [
					'lastName' => ['type' => 'text', 'value' => ['zxx' => $last]],
					'firstName' => ['type' => 'text', 'value' => ['zxx' => $first]],
					'amount' => ['type' => 'number', 'value' => ['zxx' => $amount]],
					'start' => ['type' => 'datetime', 'value' => ['zxx' => $start]],
				],
			]);
		}
		foreach ([
			[[], ['c', 'b', 'a']],
			[['sort' => 'name', 'dir' => 'desc'], ['a', 'b', 'c']],
			[['sort' => 'amount'], ['c', 'a', 'b']],
			[['sort' => 'start'], ['b', 'a', 'c']],
		] as [$query, $expected]) {
			$response = $this->makeRequest('GET', '/cp/collection/test-sorted', ['query' => $query]);
			$this->assertResponseOk($response);
			$html = $this->getHtmlResponse($response);
			foreach ($expected as $index => $uid) {
				$this->assertHtmlNodeExists('//tbody/tr[' . ($index + 1) . '][@data-uid="' . $uid . '"]', $html);
			}
		}
	}

	public function testSeveralCreatableTypesShareOneMenuButton(): void
	{
		$response = $this->makeRequest('GET', '/cp/collection/test-mixed');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists('//button[@popovertarget="collection-create"]', $html);

		foreach (['test-page', 'test-article'] as $type) {
			$this->assertHtmlNodeExists(
				"//*[@id=\"collection-create\"]//a[starts-with(@href, \"/cp/node/create/{$type}\")]",
				$html,
			);
		}
	}

	public function testPanelCollectionFormatsDateColumnsForConfiguredTimezone(): void
	{
		$this->createArticle('panel-date-de', 'Panel Date DE', '2026-01-01 10:00:00+01');
		// Date columns follow the panel UI language, negotiated here from the browser.
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'q' => 'Panel Date DE',
				'locale' => 'de',
			],
			'headers' => [
				'Accept-Language' => 'de',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('01.01.2026', $html);
		$this->assertStringContainsString('10:00', $html);
		$this->assertStringNotContainsString('2026-01-01 10:00:00+01', $html);
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

	public function testPanelCollectionSortLinksPreserveQueryState(): void
	{
		$this->createArticle('panel-sort-a', 'Panel Sort A');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'q' => 'Panel Sort',
				'sort' => 'changed',
				'dir' => 'desc',
				'limit' => '10',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString(
			'href="/cp/collection/test-articles?q=Panel%20Sort&amp;sort=changed&amp;dir=asc&amp;limit=10"',
			$html,
		);
	}

	public function testPanelCollectionPaginationLinksPreserveQueryState(): void
	{
		$changed = '2026-01-01 10:00:00+00';
		$this->createArticle('panel-link-a', 'Panel Link A', $changed);
		$this->createArticle('panel-link-b', 'Panel Link B', $changed);
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'q' => 'Panel Link',
				'sort' => 'title',
				'dir' => 'asc',
				'limit' => '1',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString(
			'href="/cp/collection/test-articles?q=Panel%20Link&amp;sort=title&amp;dir=asc&amp;limit=1&amp;offset=1"',
			$html,
		);
	}

	public function testPanelCollectionClearSearchPreservesQueryState(): void
	{
		$this->createArticle('panel-clear-a', 'Panel Clear A');
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'q' => 'Panel Clear',
				'sort' => 'title',
				'dir' => 'asc',
				'limit' => '10',
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Clear search', $html);
		$this->assertStringContainsString(
			'href="/cp/collection/test-articles?sort=title&amp;dir=asc&amp;limit=10"',
			$html,
		);
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

	public function testPanelCollectionRejectsInvalidView(): void
	{
		$response = $this->makeRequest('GET', '/cp/collection/test-articles', [
			'query' => [
				'view' => 'grid',
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
		$this->assertStringContainsString('class="page cms-collection"', $html);
		$this->assertStringNotContainsString('<!DOCTYPE html>', $html);
		$this->assertStringNotContainsString('class="panel"', $html);
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
			'title' => ['en' => $title],
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

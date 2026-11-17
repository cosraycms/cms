<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestFulltextCollection;
use Cosray\Tests\Fixtures\Collection\TestFulltextMixedCollection;
use Cosray\Tests\Fixtures\Node\FulltextDocument;
use Cosray\Tests\Fixtures\Node\FulltextPage;

final class PanelCollectionFulltextTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->app = $this->createApp(['api.path' => '/api']);
		$this->authenticateAs('editor');

		// Through the API, so the store indexes them as an editor's save would.
		foreach ([
			'fts-panel-title' => ['Zebra crossing', ''],
			'fts-panel-body' => ['Road works', 'Cars stop at the zebra and wait'],
			'fts-panel-none' => ['Nothing here', 'Plain text'],
		] as $uid => [$name, $body]) {
			$response = $this->makeRequest('PUT', "/api/nodes/{$uid}", [
				'body' => json_encode([
					'type' => 'fulltext-page',
					'published' => false,
					'content' => [
						'name' => ['value' => ['en' => $name]],
						'body' => ['value' => ['en' => $body]],
					],
				]),
				'headers' => ['Content-Type' => 'application/json'],
			]);
			$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		}

		$response = $this->makeRequest('PUT', '/api/nodes/fts-panel-document', [
			'body' => json_encode([
				'type' => 'fulltext-document',
				'published' => false,
				'content' => ['body' => ['value' => ['zxx' => 'A zebra in the notes']]],
			]),
			'headers' => ['Content-Type' => 'application/json'],
		]);
		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(FulltextPage::class);
		$bootstrap->node(FulltextDocument::class);
		$bootstrap->collection(TestFulltextCollection::class);
		$bootstrap->collection(TestFulltextMixedCollection::class);

		return $bootstrap;
	}

	public function testSearchFindsContentAndShowsWhereItMatched(): void
	{
		$response = $this->makeRequest('GET', '/panel/collection/test-fulltext', ['query' => ['q' => 'zebra']]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		// Unpublished entries without a URL are found too; the title hit ranks first.
		$this->assertHtmlNodeCount(2, '//tbody//tr[@data-uid]', $html);
		$this->assertHtmlNodeExists('//tbody/tr[1][@data-uid="fts-panel-title"]', $html);
		$this->assertHtmlNodeMissing(
			'//tr[@data-uid="fts-panel-title"]//*[contains(@class, "cms-list-snippet")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//tr[@data-uid="fts-panel-body"]//*[contains(@class, "cms-list-snippet")]/mark[normalize-space()="zebra"]',
			$html,
		);
		// Ranked by relevance, so no column claims the order.
		$this->assertHtmlNodeMissing('//th[@aria-sort]', $html);
	}

	public function testAChosenColumnOrdersTheHits(): void
	{
		$response = $this->makeRequest('GET', '/panel/collection/test-fulltext', [
			'query' => ['q' => 'zebra', 'sort' => 'title', 'dir' => 'asc'],
		]);

		$html = $this->getHtmlResponse($response);
		// Against relevance, which puts the title hit first.
		$this->assertHtmlNodeExists('//tbody/tr[1][@data-uid="fts-panel-body"]', $html);
		$this->assertHtmlNodeExists('//tbody/tr[2][@data-uid="fts-panel-title"]', $html);
		$this->assertHtmlNodeExists('//th[@aria-sort="ascending"]', $html);
	}

	public function testAWordMayNameTheTypeWhereTheListingShowsIt(): void
	{
		$rows = fn(string $collection, string $q): array => $this->rows($this->listing($collection, $q));

		$this->assertSame(['fts-panel-document'], $rows('test-fulltext-mixed', 'zebra document'));
		$this->assertSame(['fts-panel-document'], $rows('test-fulltext-mixed', 'FulltextDocument'));
		$this->assertSame(['fts-panel-title', 'fts-panel-body'], $rows('test-fulltext-mixed', 'zebra -document'));
		// A listing of one type has no type column, so its search leaves the type alone.
		$this->assertSame([], $rows('test-fulltext', 'FulltextPage'));
	}

	public function testASearchMarksTheWordsItsColumnsShow(): void
	{
		$html = $this->listing('test-fulltext-mixed', 'zebra document');
		$document = '//tr[@data-uid="fts-panel-document"]';

		$this->assertHtmlNodeExists(
			$document . '//*[contains(@class, "cms-list-value")]/mark[normalize-space()="FulltextDocument"]',
			$html,
		);
		// Only the document explains the other word.
		$this->assertHtmlNodeExists(
			$document . '//*[contains(@class, "cms-list-snippet")]/mark[normalize-space()="zebra"]',
			$html,
		);

		$html = $this->listing('test-fulltext-mixed', 'ZEBRA crossing');
		$title = '//tr[@data-uid="fts-panel-title"]';

		$this->assertHtmlNodeExists(
			$title . '//a[contains(@class, "cms-list-value")]/mark[normalize-space()="Zebra"]',
			$html,
		);
		$this->assertHtmlNodeMissing($title . '//*[contains(@class, "cms-list-snippet")]', $html);
		$this->assertHtmlNodeMissing(
			'//tr[@data-uid="fts-panel-body"]//*[contains(@class, "cms-list-value")]/mark',
			$html,
		);
	}

	public function testAWordNamingATypeOffersItsFilterInsteadOfTheWord(): void
	{
		$chip = '//a[contains(@class, "cms-collection-type-suggestion")]';

		$this->assertHtmlNodeExists(
			$chip
				. '[contains(@href, "type=fulltext-document")][contains(@href, "q=zebra")]'
				. '[not(contains(@href, "FulltextDocument"))]',
			$this->listing('test-fulltext-mixed', 'zebra FulltextDocument'),
		);
		$this->assertHtmlNodeMissing($chip, $this->listing('test-fulltext-mixed', 'zebra OR FulltextDocument'));
		$this->assertHtmlNodeMissing($chip, $this->listing('test-fulltext-mixed', 'zebra'));
		$this->assertHtmlNodeMissing($chip, $this->listing('test-fulltext', 'FulltextPage'));
	}

	private function listing(string $collection, string $q): string
	{
		$response = $this->makeRequest('GET', "/panel/collection/{$collection}", ['query' => ['q' => $q]]);
		$this->assertResponseOk($response);

		return $this->getHtmlResponse($response);
	}

	/** @return list<string> */
	private function rows(string $html): array
	{
		preg_match_all('/<tr[^>]*\sdata-uid="([^"]+)"/', $html, $matches);

		return $matches[1];
	}
}

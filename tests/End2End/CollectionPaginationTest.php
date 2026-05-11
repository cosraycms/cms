<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\End2End;

use Celemas\Cms\Config;
use Celemas\Cms\Plugin;
use Celemas\Cms\Tests\End2EndTestCase;
use Celemas\Cms\Tests\Fixtures\Collection\TestArticlesCollection;

final class CollectionPaginationTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types', 'sample-nodes');
		$this->authenticateAs('editor');
	}

	protected function createPlugin(Config $config): Plugin
	{
		$plugin = parent::createPlugin($config);
		$plugin->collection(TestArticlesCollection::class);

		return $plugin;
	}

	public function testCollectionEndpointReturnsPaginationPayload(): void
	{
		$this->createArticle('page-node-a', 'Page node A');
		$this->createArticle('page-node-b', 'Page node B');

		$response = $this->makeRequest('GET', '/api/collection/test-articles', [
			'query' => [
				'limit' => '2',
				'offset' => '1',
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);

		$this->assertSame(2, $payload['limit']);
		$this->assertSame(1, $payload['offset']);
		$this->assertSame('changed', $payload['sort']);
		$this->assertSame('desc', $payload['dir']);
		$this->assertGreaterThanOrEqual(4, $payload['total']);
		$this->assertCount(2, $payload['nodes']);
	}

	public function testCollectionEndpointSearchesOnServer(): void
	{
		$this->createArticle('needle-node', 'Needle title');

		$response = $this->makeRequest('GET', '/api/collection/test-articles', [
			'query' => [
				'q' => 'needle',
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);

		$this->assertSame(1, $payload['total']);
		$this->assertCount(1, $payload['nodes']);
		$this->assertSame('needle-node', $payload['nodes'][0]['uid']);
	}

	public function testCollectionEndpointAppliesStableOrdering(): void
	{
		$changed = '2025-01-02 03:04:05+00';
		$this->createArticle('stable-b', 'Stable B', $changed);
		$this->createArticle('stable-a', 'Stable A', $changed);

		$response = $this->makeRequest('GET', '/api/collection/test-articles', [
			'query' => [
				'q' => 'stable-',
				'sort' => 'changed',
				'dir' => 'desc',
				'limit' => '10',
			],
		]);

		$payload = $this->assertJsonResponse($response, 200);

		$this->assertCount(2, $payload['nodes']);
		$this->assertSame('stable-a', $payload['nodes'][0]['uid']);
		$this->assertSame('stable-b', $payload['nodes'][1]['uid']);
	}

	public function testCollectionEndpointRejectsInvalidLimit(): void
	{
		$response = $this->makeRequest('GET', '/api/collection/test-articles', [
			'query' => [
				'limit' => 'nope',
			],
		]);

		$this->assertResponseStatus(400, $response);
	}

	private function createArticle(
		string $uid,
		string $title,
		string $changed = 'now()',
	): void {
		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-article'",
		)->one();
		$this->assertNotEmpty($type);

		$this->createTestNode([
			'uid' => $uid,
			'type' => (int) $type['type'],
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
}

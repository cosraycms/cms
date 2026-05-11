<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\End2End;

use Celemas\Cms\Config;
use Celemas\Cms\Plugin;
use Celemas\Cms\Tests\End2EndTestCase;
use Celemas\Cms\Tests\Fixtures\Collection\TestArticlesCollection;
use Celemas\Cms\Tests\Fixtures\Collection\TestHierarchyCollection;

final class OldPanelCollectionsTest extends End2EndTestCase
{
	protected function createPlugin(Config $config): Plugin
	{
		$plugin = parent::createPlugin($config);
		$content = $plugin->section('Inhalt');
		$content->collection(TestArticlesCollection::class);
		$content->section('Unterbereich')->collection(TestHierarchyCollection::class);

		return $plugin;
	}

	public function testCollectionsEndpointReturnsLegacyFlatNavigationPayload(): void
	{
		$this->authenticateAs('superuser');
		$response = $this->makeRequest('GET', '/panel/api/collections');
		$payload = $this->assertJsonResponse($response, 200);

		$summary = array_map(
			static fn(array $item): string => (
				($item['type'] ?? '') . ':' . (string) ($item['slug'] ?? $item['name'] ?? '')
			),
			$payload,
		);

		$this->assertSame(
			[
				'section:Inhalt',
				'collection:test-articles',
				'section:Unterbereich',
				'collection:test-hierarchy',
			],
			$summary,
		);

		foreach ($payload as $item) {
			$this->assertArrayHasKey('meta', $item);
			$this->assertIsArray($item['meta']);
			$this->assertArrayHasKey('children', $item);
			$this->assertSame([], $item['children']);
		}
	}
}

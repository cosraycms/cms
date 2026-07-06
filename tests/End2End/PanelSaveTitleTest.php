<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

/**
 * Saving through the panel materializes the node title into `nodes.title`
 * (Store::persist writes it in the same UPDATE as the content).
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelSaveTitleTest extends End2EndTestCase
{
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

		return $plugin;
	}

	public function testSaveMaterializesAndTracksTitle(): void
	{
		$this->article('e2e-title-node');

		$this->save('e2e-title-node', 'Brewing');
		// The active locale is English; German falls back to it, so the title
		// resolves the same everywhere and collapses to the neutral key.
		$this->assertEquals(['zxx' => 'Brewing'], $this->titleOf('e2e-title-node'));

		// A re-save keeps the materialized title in step with the edit.
		$this->save('e2e-title-node', 'Roasting');
		$this->assertEquals(['zxx' => 'Roasting'], $this->titleOf('e2e-title-node'));
	}

	public function testSaveWithoutTitleMaterializesEmpty(): void
	{
		$this->article('e2e-title-empty');

		// An empty title resolves to no string, so the map is empty and the
		// display layer supplies an "Untitled" fallback.
		$this->save('e2e-title-empty', '');

		$this->assertSame([], $this->titleOf('e2e-title-empty'));
	}

	private function article(string $uid): void
	{
		$type = $this->db()->execute("SELECT type FROM cms.types WHERE handle = 'test-article'")->first();
		$typeId = $type ? (int) $type['type'] : $this->createTestType('test-article');

		$this->createTestNode(['uid' => $uid, 'type' => $typeId, 'published' => true]);
	}

	private function save(string $uid, string $title): void
	{
		$response = $this->makeRequest('POST', "/cp/collection/test-articles/{$uid}", [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['content' => ['title' => ['value' => ['en' => $title]]]],
		]);

		$this->assertResponseOk($response);
	}

	/**
	 * @return array<string, string>
	 */
	private function titleOf(string $uid): array
	{
		$title = $this->db()->execute(
			'SELECT title FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one()['title'];

		return json_decode((string) $title, true);
	}
}

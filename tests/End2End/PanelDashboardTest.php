<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Quma\Database;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Contract\DashboardCard;
use Cosray\Panel\Dashboard\Card;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use DateTimeImmutable;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Panel\Index
 * @covers \Cosray\Panel\Dashboard\Entries
 * @covers \Cosray\Panel\Dashboard\Drafts
 * @covers \Cosray\Panel\Dashboard\Media
 */
final class PanelDashboardTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->db()->execute('UPDATE cms.nodes SET deleted = now() WHERE deleted IS NULL')->run();
		$this->db()->execute('DELETE FROM cms.asset_references')->run();
		$this->db()->execute('DELETE FROM cms.assets')->run();
		$this->authenticateAs('editor');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->section('Content')->collection(TestArticlesCollection::class);
		$bootstrap
			->dashboard
			->add(AutowiredDashboardCard::class)
			->add(new InstanceDashboardCard())
			->add(HiddenDashboardCard::class);

		return $bootstrap;
	}

	public function testDashboardRendersDefaultAndCustomCardsWithRecentEntries(): void
	{
		$type = (int) $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-page'",
		)->one()['type'];
		$entries = (int) $this->db()->dashboard->entries()->one()['total'];
		$changes = $this->db()->dashboard->changes()->one();

		for ($index = 0; $index < 7; $index++) {
			$this->createDashboardNode(
				$type,
				"dashboard-recent-{$index}",
				"Recent {$index}",
				published: ($index % 2) === 1,
				changed: new DateTimeImmutable("+1 day -{$index} hours"),
			);
		}

		$this->createDashboardNode(
			$type,
			'dashboard-old-draft',
			'Old draft',
			published: false,
			changed: new DateTimeImmutable('-10 days'),
		);
		$this->createDashboardNode(
			$type,
			'dashboard-deleted',
			'Deleted',
			published: false,
			changed: new DateTimeImmutable(),
			deleted: true,
		);
		$this->createAsset('dashboard-asset-a', 1024);
		$this->createAsset('dashboard-asset-b', 2048);
		$this->createDraft('dashboard-recent-1', new DateTimeImmutable('-1 hour'));
		$this->createDraft('dashboard-recent-3', new DateTimeImmutable('-30 days'));

		$html = $this->html();
		$entryTotal = $entries + 8;
		$changesTotal = (int) $changes['total'] + 2;
		$recentChanges = (int) $changes['recent'] + 1;
		$this->assertHtmlNodeExists(
			'//*[contains(concat(" ", normalize-space(@class), " "), " card ")][span[contains(concat(" ", normalize-space(@class), " "), " label ") and normalize-space(.)="Entries"]][strong[contains(concat(" ", normalize-space(@class), " "), " value ") and normalize-space(.)="'
				. $entryTotal
				. '"]][span[contains(concat(" ", normalize-space(@class), " "), " note ") and normalize-space(.)="across 1 collection"]]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//*[contains(concat(" ", normalize-space(@class), " "), " card ")][span[contains(concat(" ", normalize-space(@class), " "), " label ") and normalize-space(.)="Pending changes"]][strong[contains(concat(" ", normalize-space(@class), " "), " value ") and normalize-space(.)="'
				. $changesTotal
				. '"]][span[contains(concat(" ", normalize-space(@class), " "), " note ") and normalize-space(.)="'
				. $recentChanges
				. ' since last week"]]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//a[@href="/cp/media" and @hx-target="#frame" and contains(concat(" ", normalize-space(@class), " "), " card ")][span[contains(concat(" ", normalize-space(@class), " "), " label ") and normalize-space(.)="Media files"]][strong[contains(concat(" ", normalize-space(@class), " "), " value ") and normalize-space(.)="2"]][span[contains(concat(" ", normalize-space(@class), " "), " note ") and normalize-space(.)="3.0 KB used"]]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//*[contains(concat(" ", normalize-space(@class), " "), " card ")][span[contains(concat(" ", normalize-space(@class), " "), " label ") and normalize-space(.)="Custom total"]][strong[contains(concat(" ", normalize-space(@class), " "), " value ") and normalize-space(.)="'
				. $entryTotal
				. ' total"]]',
			$html,
		);

		$this->assertStringContainsString('<span class="label">Instance card</span>', $html);
		$this->assertStringNotContainsString('Hidden card', $html);
		$this->assertStringNotContainsString('Deleted', $html);
		$this->assertStringNotContainsString('Old draft', $html);
		$this->assertStringNotContainsString('Recent 6', $html);
		$previous = -1;

		for ($index = 0; $index < 6; $index++) {
			$position = strpos($html, "Recent {$index}");
			$this->assertNotFalse($position);
			$this->assertGreaterThan($previous, $position);
			$previous = $position;
		}
		$this->assertStringContainsString('<span class="type">Test Page</span>', $html);
		$this->assertStringContainsString('<span class="status sr-only">Unpublished</span>', $html);
		$this->assertSame(2, substr_count($html, 'cms-status is-changes'));
		$this->assertStringContainsString('<span class="status sr-only">Published</span>', $html);
		$this->assertHtmlNodeExists(
			'//time[contains(concat(" ", normalize-space(@class), " "), " changed ") and string-length(@datetime) > 0 and string-length(normalize-space(.)) > 0]',
			$html,
		);
	}

	public function testDashboardShowsTheRecentEmptyState(): void
	{
		$html = $this->html();

		$this->assertStringContainsString('No entries have been edited yet.', $html);
		$this->assertStringContainsString('Custom total', $html);
	}

	public function testDisabledDashboardLeavesTheMastheadAndOpensTheContentArea(): void
	{
		$this->app = $this->createApp(['panel.dashboard' => false]);

		$response = $this->makeRequest('GET', '/cp');
		$this->assertSame(303, $response->getStatusCode());
		$this->assertSame('/cp/collection/test-articles', $response->getHeaderLine('Location'));

		$response = $this->makeRequest('GET', '/cp/collection/test-articles');
		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);

		$this->assertHtmlNodeMissing('//*[@id="area-nav"]/a[@href="/cp"]', $html);
		$this->assertHtmlNodeExists(
			'//a[contains(concat(" ", normalize-space(@class), " "), " logo ") and @href="/cp/collection/test-articles"]',
			$html,
		);
	}

	private function html(): string
	{
		$response = $this->makeRequest('GET', '/cp');
		$this->assertResponseOk($response);

		return $this->getHtmlResponse($response);
	}

	private function createDashboardNode(
		int $type,
		string $uid,
		string $title,
		bool $published,
		DateTimeImmutable $changed,
		bool $deleted = false,
	): void {
		$this->db()->execute(
			'INSERT INTO cms.nodes
				(uid, published, hidden, locked, type, creator, editor, created, changed, deleted, content, title)
			 VALUES
				(:uid, :published, false, false, :type, 1, 1, :changed, :changed, :deleted, :content::jsonb, :title::jsonb)',
			[
				'uid' => $uid,
				'published' => $published,
				'type' => $type,
				'changed' => $changed->format(DATE_ATOM),
				'deleted' => $deleted ? new DateTimeImmutable()->format(DATE_ATOM) : null,
				'content' => '{}',
				'title' => json_encode(['en' => $title]),
			],
		)->run();
	}

	private function createDraft(string $uid, DateTimeImmutable $changed): void
	{
		$this->db()->execute(
			"INSERT INTO cms.drafts (node, editor, content, changed)
			 SELECT node, 1, '{}'::jsonb, :changed FROM cms.nodes WHERE uid = :uid",
			['uid' => $uid, 'changed' => $changed->format(DATE_ATOM)],
		)->run();
	}

	private function createAsset(string $uid, int $bytes): void
	{
		$this->db()->execute(
			'INSERT INTO cms.assets (uid, disk, key, filename, mime, bytes, creator)
			 VALUES (:uid, :disk, :key, :filename, :mime, :bytes, 1)',
			[
				'uid' => $uid,
				'disk' => 'local',
				'key' => "dashboard/{$uid}.jpg",
				'filename' => "{$uid}.jpg",
				'mime' => 'image/jpeg',
				'bytes' => $bytes,
			],
		)->run();
	}
}

final readonly class AutowiredDashboardCard implements DashboardCard
{
	public function __construct(
		private Database $db,
	) {}

	public function card(): Card
	{
		$row = $this->db->dashboard->entries()->one();

		return new Card('Custom total', (int) ($row['total'] ?? 0) . ' total');
	}
}

final class InstanceDashboardCard implements DashboardCard
{
	public function card(): Card
	{
		return new Card('Instance card', 'Ready');
	}
}

final class HiddenDashboardCard implements DashboardCard
{
	public function card(): ?Card
	{
		return null;
	}
}

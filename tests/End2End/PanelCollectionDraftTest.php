<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestDraftPagesCollection;
use Cosray\Tests\Fixtures\Node\TestDraftPage;

/**
 * Working copies in the listing: the changes badge, and what bulk publish
 * and unpublish do with them.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelCollectionDraftTest extends End2EndTestCase
{
	private int $typeId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
		$this->typeId = $this->createTestType('test-draft-page');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->node(TestDraftPage::class);
		$plugin->section('Inhalt')->collection(TestDraftPagesCollection::class);

		return $plugin;
	}

	public function testListingShowsEachRowsStagesAsDots(): void
	{
		$this->createPage('listing-clean', 'Clean', published: true);
		$this->createPage('listing-offline', 'Offline', published: false);
		$this->createPage('listing-changed', 'Changed', published: true, draftTitle: 'Pending');

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/collection/test-draft-pages'));

		$this->assertHtmlNodeExists($this->dot('listing-changed', 'website', 'online'), $html);
		$this->assertHtmlNodeExists($this->dot('listing-changed', 'draft', 'present'), $html);
		$this->assertHtmlNodeExists($this->dot('listing-clean', 'draft', 'none'), $html);
		// Never published: its content is the editorial team's draft.
		$this->assertHtmlNodeExists($this->dot('listing-offline', 'website', 'none'), $html);
		$this->assertHtmlNodeExists($this->dot('listing-offline', 'draft', 'present'), $html);
		$this->assertHtmlNodeExists(
			'//dialog[@data-dialog="bulk-publish"]//label[@data-bulk-option]/input[@name="changes"]',
			$html,
		);
	}

	public function testBulkPublishLeavesWorkingCopiesAloneUnlessAsked(): void
	{
		$this->createPage('bulk-changes-offline', 'Offline', published: false);
		$this->createPage('bulk-changes-pending', 'Live', published: true, draftTitle: 'Pending');

		$response = $this->bulk(['bulk-changes-offline', 'bulk-changes-pending'], 'published');

		$this->assertStringContainsString(
			'notice=' . rawurlencode('published:2'),
			$response->getHeaderLine('Location'),
		);
		$this->assertTrue($this->published('bulk-changes-offline'));
		$this->assertSame('Live', $this->liveTitle('bulk-changes-pending'));
		$this->assertTrue($this->hasDraft('bulk-changes-pending'));
	}

	public function testBulkPublishWithTheChangesOptionReleasesWorkingCopies(): void
	{
		$this->createPage('bulk-release-offline', 'Offline', published: false);
		$this->createPage('bulk-release-pending', 'Live', published: true, draftTitle: 'Released');
		$this->createPage('bulk-release-clean', 'Clean', published: true);

		$response = $this->bulk(
			['bulk-release-offline', 'bulk-release-pending', 'bulk-release-clean'],
			'published',
			['changes' => '1'],
		);

		$this->assertStringContainsString(
			'notice=' . rawurlencode('published:3,changes-published:1'),
			$response->getHeaderLine('Location'),
		);
		$this->assertTrue($this->published('bulk-release-offline'));
		$this->assertSame('Released', $this->liveTitle('bulk-release-pending'));
		$this->assertFalse($this->hasDraft('bulk-release-pending'));
	}

	public function testBulkUnpublishKeepsTheWorkingCopy(): void
	{
		$this->createPage('bulk-offline', 'Live', published: true, draftTitle: 'Pending');

		$response = $this->bulk(['bulk-offline'], 'unpublished');

		$this->assertStringContainsString(
			'notice=' . rawurlencode('unpublished:1'),
			$response->getHeaderLine('Location'),
		);
		$this->assertFalse($this->published('bulk-offline'));
		$this->assertSame('Live', $this->liveTitle('bulk-offline'));
		$this->assertTrue($this->hasDraft('bulk-offline'));
	}

	/** @param list<string> $uids */
	public function testStatusFiltersNarrowTheListAndCountEachStage(): void
	{
		$this->createPage('filter-clean', 'Clean', published: true);
		$this->createPage('filter-new', 'New', published: false);
		$this->createPage('filter-changed', 'Changed', published: true, draftTitle: 'Pending');

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/collection/test-draft-pages', [
			'query' => ['stage' => 'draft'],
		]));

		$this->assertHtmlNodeExists('//tr[@data-uid="filter-changed"]', $html);
		$this->assertHtmlNodeExists('//tr[@data-uid="filter-new"]', $html);
		$this->assertHtmlNodeMissing('//tr[@data-uid="filter-clean"]', $html);
		$this->assertHtmlNodeExists(
			'//nav[contains(@class, "cms-stage-filters")]/a[@aria-current="true"][contains(., "With draft")]/*[@class="count"][.="2"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//nav[contains(@class, "cms-stage-filters")]/a[contains(., "Never published")]/*[@class="count"][.="1"]',
			$html,
		);
		$this->assertResponseStatus(400, $this->makeRequest('GET', '/panel/collection/test-draft-pages', [
			'query' => ['stage' => 'nonsense'],
		]));
	}

	private function dot(string $uid, string $stage, string $state): string
	{
		return "//tr[@data-uid='{$uid}']//*[contains(@class, 'cms-status-dots')]/*[@data-stage='{$stage}' and @data-state='{$state}']";
	}

	private function bulk(array $uids, string $state, array $extra = []): object
	{
		$response = $this->makeRequest('POST', '/panel/collection/test-draft-pages/bulk/publish', [
			'body' => ['nodes' => $uids, 'state' => $state] + $extra,
		]);
		$this->assertResponseStatus(303, $response);

		return $response;
	}

	private function createPage(string $uid, string $title, bool $published, ?string $draftTitle = null): void
	{
		$nodeId = $this->createTestNode([
			'uid' => $uid,
			'type' => $this->typeId,
			'published' => $published,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
		]);

		if ($draftTitle === null) {
			return;
		}

		$this->db()->execute(
			'INSERT INTO cms.drafts (node, editor, content) VALUES (:node, 1, :content::jsonb)',
			[
				'node' => $nodeId,
				'content' => json_encode(['title' => ['type' => Text::class, 'value' => ['en' => $draftTitle]]]),
			],
		)->run();
	}

	private function published(string $uid): bool
	{
		return (bool) $this->db()->execute('SELECT published FROM cms.nodes WHERE uid = :uid', [
			'uid' => $uid,
		])->one()['published'];
	}

	private function liveTitle(string $uid): string
	{
		$row = $this->db()->execute('SELECT content FROM cms.nodes WHERE uid = :uid', ['uid' => $uid])->one();

		return json_decode((string) $row['content'], true)['title']['value']['en'];
	}

	private function hasDraft(string $uid): bool
	{
		return (bool) $this->db()->execute(
			'SELECT count(*) AS n FROM cms.drafts d JOIN cms.nodes n ON n.node = d.node WHERE n.uid = :uid',
			['uid' => $uid],
		)->one()['n'];
	}
}

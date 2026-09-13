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

	public function testListingBadgesNodesWithAWorkingCopy(): void
	{
		$this->createPage('listing-clean', 'Clean', published: true);
		$this->createPage('listing-offline', 'Offline', published: false);
		$this->createPage('listing-changed', 'Changed', published: true, draftTitle: 'Pending');

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/collection/test-draft-pages'));

		$this->assertHtmlNodeExists(
			'//tr[@data-uid="listing-changed"]//span[contains(@class, "cms-status") and contains(@class, "is-published")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//tr[@data-uid="listing-changed"]//span[contains(@class, "cms-status") and contains(@class, "is-changes")][normalize-space(.)="Changes"]',
			$html,
		);
		$this->assertHtmlNodeMissing('//tr[@data-uid="listing-clean"]//span[contains(@class, "is-changes")]', $html);
		$this->assertHtmlNodeExists(
			'//tr[@data-uid="listing-offline"]//span[contains(@class, "is-unpublished")][normalize-space(.)="Unpublished"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//dialog[@data-bulk-dialog="publish"]//label[@data-bulk-option]/input[@name="changes"]',
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

	public function testBulkUnpublishFoldsTheWorkingCopyIn(): void
	{
		$this->createPage('bulk-fold', 'Live', published: true, draftTitle: 'Folded');

		$response = $this->bulk(['bulk-fold'], 'unpublished');

		$this->assertStringContainsString(
			'notice=' . rawurlencode('unpublished:1'),
			$response->getHeaderLine('Location'),
		);
		$this->assertFalse($this->published('bulk-fold'));
		$this->assertSame('Folded', $this->liveTitle('bulk-fold'));
		$this->assertFalse($this->hasDraft('bulk-fold'));
	}

	/** @param list<string> $uids */
	private function bulk(array $uids, string $state, array $extra = []): object
	{
		$response = $this->makeRequest('POST', '/cp/collection/test-draft-pages/bulk/publish', [
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

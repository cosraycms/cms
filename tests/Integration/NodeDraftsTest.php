<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Core\Exception\HttpBadRequest;
use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Reference;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Node\PathManager;
use Cosray\Node\Store;
use Cosray\References\Rebuild;
use Cosray\References\Usage;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeDraftsTest extends IntegrationTestCase
{
	private Context $context;
	private Cms $cms;
	private Store $store;
	private int $typeId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->context = $this->createContext();
		$services = Services::withDefaults();
		$this->cms = new Cms($this->context, $services);
		$this->store = new Store(
			$this->context->db,
			new PathManager(),
			$services->types,
			$this->cms->nodeFactory()->uid(),
			factory: $this->cms->nodeFactory(),
			cms: $this->cms,
			context: $this->context,
		);
		$this->typeId = $this->createTestType('test-draft-page');
	}

	public function testDraftSaveLeavesTheLiveRowAloneAndUpdatesInPlace(): void
	{
		$this->createPage('drafts-page', 'Live');

		$this->store->draft(
			$this->node('drafts-page'),
			$this->payload('drafts-page', 'First'),
			$this->locales(),
			Actor::system(),
		);
		$this->store->draft(
			$this->node('drafts-page'),
			$this->payload('drafts-page', 'Second'),
			$this->locales(),
			Actor::system(),
		);

		$this->assertSame('Live', $this->liveTitle('drafts-page'));
		$this->assertSame('Second', $this->draftTitle('drafts-page'));
		$this->assertSame(1, $this->rows('drafts', 'drafts-page'));
		$this->assertSame(1, $this->rows('drafts_history', 'drafts-page'));
	}

	public function testDraftCarriesHandleAndPathsUntilPublished(): void
	{
		$this->createPage('drafts-settings', 'Live');
		$payload = $this->payload('drafts-settings', 'Changed')
		+ [
			'handle' => 'drafted-handle',
			'paths' => ['en' => '/drafted-path'],
		];

		$this->store->draft($this->node('drafts-settings'), $payload, $this->locales(), Actor::system());

		$settings = json_decode((string) $this->row('drafts', 'drafts-settings')['settings'], true);
		$this->assertSame('drafted-handle', $settings['handle']);
		$this->assertSame(['en' => '/drafted-path'], $settings['paths']);
		$this->assertNull($this->handle('drafts-settings'));
		$this->assertNull($this->activePath('drafts-settings'));

		$this->store->publishDraft($this->node('drafts-settings'), $this->locales(), Actor::system());

		$this->assertSame('drafted-handle', $this->handle('drafts-settings'));
		$this->assertSame('/drafted-path', $this->activePath('drafts-settings'));
	}

	public function testDraftReferencesAreIndexedAndReplacedOnPublish(): void
	{
		$this->createTestNode(['uid' => 'drafts-target', 'type' => $this->typeId]);
		$this->createPage('drafts-referrer', 'Live');
		$payload = $this->payload('drafts-referrer', 'Links', related: ['drafts-target']);

		$this->store->draft($this->node('drafts-referrer'), $payload, $this->locales(), Actor::system());

		$this->assertSame(['draft'], $this->referenceOwners('drafts-target'));
		$usage = new Usage($this->db())->forNode('drafts-target');
		$this->assertCount(1, $usage);
		$this->assertSame('draft', $usage[0]['ownerType']);
		$this->assertSame('test-draft-page', $usage[0]['nodeType']);
		$this->assertTrue($usage[0]['published']);

		$this->store->publish($this->node('drafts-referrer'), $payload, $this->locales(), Actor::system());

		$this->assertSame(['node'], $this->referenceOwners('drafts-target'));
		$this->assertSame('Links', $this->liveTitle('drafts-referrer'));
		$this->assertSame(0, $this->rows('drafts', 'drafts-referrer'));
	}

	public function testTheHistoryOutlivesTheWorkingCopyAndNamesEachEditor(): void
	{
		$writer = $this->createTestUser(['uid' => 'drafts-writer']);
		$photographer = $this->createTestUser(['uid' => 'drafts-photographer']);
		$this->createPage('drafts-timeline', 'Live');

		$this->store->draft(
			$this->node('drafts-timeline'),
			$this->payload('drafts-timeline', 'Text written'),
			$this->locales(),
			new Actor($writer),
		);
		$this->store->draft(
			$this->node('drafts-timeline'),
			$this->payload('drafts-timeline', 'Pictures added') + ['handle' => 'timeline'],
			$this->locales(),
			new Actor($photographer),
		);
		$this->store->publishDraft($this->node('drafts-timeline'), $this->locales(), Actor::system());

		$history = $this->db()->execute(
			'SELECT h.editor, h.content, h.settings, h.outcome, h.created FROM cms.drafts_history h
				JOIN cms.nodes n ON n.node = h.node WHERE n.uid = :uid ORDER BY h.changed',
			['uid' => 'drafts-timeline'],
		)->all();
		$this->assertCount(2, $history);
		$this->assertSame(
			[$writer, $photographer],
			array_map(static fn(array $row): int => (int) $row['editor'], $history),
		);
		$this->assertSame('Pictures added', json_decode((string) $history[1]['content'], true)['title']['value']['en']);
		$this->assertSame('timeline', json_decode((string) $history[1]['settings'], true)['handle']);
		$this->assertSame(['saved', 'published'], array_column($history, 'outcome'));
		$this->assertSame($history[0]['created'], $history[1]['created']);
		$this->assertSame('Pictures added', $this->liveTitle('drafts-timeline'));
	}

	public function testDiscardRecordsTheLastStateOfTheWorkingCopy(): void
	{
		$this->createPage('drafts-discard-history', 'Live');
		$this->store->draft(
			$this->node('drafts-discard-history'),
			$this->payload('drafts-discard-history', 'Thrown away'),
			$this->locales(),
			Actor::system(),
		);

		$this->store->discard($this->node('drafts-discard-history'));

		$this->assertSame(0, $this->rows('drafts', 'drafts-discard-history'));
		$this->assertSame(1, $this->rows('drafts_history', 'drafts-discard-history'));
		$this->assertSame('discarded', $this->row('drafts_history', 'drafts-discard-history')['outcome']);
		$this->assertSame(
			'Thrown away',
			json_decode(
				(string) $this->row('drafts_history', 'drafts-discard-history')['content'],
				true,
			)['title']['value']['en'],
		);
	}

	public function testDiscardDropsTheWorkingCopyAndItsReferences(): void
	{
		$this->createTestNode(['uid' => 'drafts-discard-target', 'type' => $this->typeId]);
		$this->createPage('drafts-discard', 'Live');
		$payload = $this->payload('drafts-discard', 'Gone', related: ['drafts-discard-target']);
		$this->store->draft($this->node('drafts-discard'), $payload, $this->locales(), Actor::system());

		$this->store->discard($this->node('drafts-discard'));

		$this->assertSame(0, $this->rows('drafts', 'drafts-discard'));
		$this->assertSame([], $this->referenceOwners('drafts-discard-target'));
		$this->assertSame('Live', $this->liveTitle('drafts-discard'));
	}

	public function testHiddenIsALiveSwitchOnADraftSave(): void
	{
		$this->createPage('drafts-hidden', 'Live');
		$payload = $this->payload('drafts-hidden', 'Changed');
		$payload['hidden'] = true;

		$this->store->draft($this->node('drafts-hidden'), $payload, $this->locales(), Actor::system());

		$this->assertTrue((bool) $this->row('nodes', 'drafts-hidden')['hidden']);
		$this->assertSame('Live', $this->liveTitle('drafts-hidden'));
	}

	public function testUnpublishFoldsTheWorkingCopyIntoTheNode(): void
	{
		$this->createPage('drafts-fold', 'Live');
		$this->store->draft(
			$this->node('drafts-fold'),
			$this->payload('drafts-fold', 'Folded'),
			$this->locales(),
			Actor::system(),
		);

		$this->store->unpublish($this->node('drafts-fold'), $this->locales(), Actor::system());

		$row = $this->row('nodes', 'drafts-fold');
		$this->assertFalse((bool) $row['published']);
		$this->assertSame('Folded', $this->liveTitle('drafts-fold'));
		$this->assertSame(0, $this->rows('drafts', 'drafts-fold'));
	}

	public function testUnpublishWithoutAWorkingCopyOnlyFlipsTheFlag(): void
	{
		$this->createPage('drafts-flip', 'Live');

		$this->store->unpublish($this->node('drafts-flip'), $this->locales(), Actor::system());

		$this->assertFalse((bool) $this->row('nodes', 'drafts-flip')['published']);
		$this->assertSame('Live', $this->liveTitle('drafts-flip'));
	}

	public function testPublishDraftReportsWhetherThereWasOne(): void
	{
		$this->createPage('drafts-release', 'Live');

		$this->assertFalse($this->store->publishDraft(
			$this->node('drafts-release'),
			$this->locales(),
			Actor::system(),
		));

		$this->store->draft(
			$this->node('drafts-release'),
			$this->payload('drafts-release', 'Released'),
			$this->locales(),
			Actor::system(),
		);

		$this->assertTrue($this->store->publishDraft($this->node('drafts-release'), $this->locales(), Actor::system()));
		$this->assertSame('Released', $this->liveTitle('drafts-release'));
		$this->assertTrue((bool) $this->row('nodes', 'drafts-release')['published']);
	}

	public function testOnlyPublishedRenderableNodesHoldAWorkingCopy(): void
	{
		$this->createPage('drafts-unpublished', 'Live', published: false);

		$this->expectException(RuntimeException::class);
		$this->store->draft(
			$this->node('drafts-unpublished'),
			$this->payload('drafts-unpublished', 'X'),
			$this->locales(),
			Actor::system(),
		);
	}

	public function testALockedNodeRefusesAWorkingCopy(): void
	{
		$this->createPage('drafts-locked', 'Live');
		$this->db()->execute('UPDATE cms.nodes SET locked = true WHERE uid = :uid', ['uid' => 'drafts-locked'])->run();
		$payload = $this->payload('drafts-locked', 'X');
		$payload['locked'] = true;

		$this->expectException(HttpBadRequest::class);
		$this->store->draft($this->node('drafts-locked'), $payload, $this->locales(), Actor::system());
	}

	public function testRebuildIndexesWorkingCopies(): void
	{
		$this->createTestNode(['uid' => 'drafts-rebuild-target', 'type' => $this->typeId]);
		$this->createPage('drafts-rebuild', 'Live');
		$payload = $this->payload('drafts-rebuild', 'Links', related: ['drafts-rebuild-target']);
		$this->store->draft($this->node('drafts-rebuild'), $payload, $this->locales(), Actor::system());
		$this->db()->execute('DELETE FROM cms.node_references')->run();

		new Rebuild($this->db())->run();

		$this->assertSame(['draft'], $this->referenceOwners('drafts-rebuild-target'));
	}

	private function createPage(string $uid, string $title, bool $published = true): void
	{
		$this->createTestNode([
			'uid' => $uid,
			'type' => $this->typeId,
			'published' => $published,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
		]);
	}

	/**
	 * @param list<string> $related
	 * @return array<string, mixed>
	 */
	private function payload(string $uid, string $title, array $related = []): array
	{
		return [
			'uid' => $uid,
			'published' => true,
			'hidden' => false,
			'locked' => false,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => $title]],
				'related' => [
					'type' => Reference::class,
					'value' => ['zxx' => array_map(static fn(string $target): array => ['uid' => $target], $related)],
				],
			],
		];
	}

	private function node(string $uid): object
	{
		$node = $this->cms->node->byUid($uid, published: null);
		$this->assertNotNull($node);

		return \Cosray\Node\Wrapper::unwrap($node);
	}

	private function locales(): \Cosray\Locales
	{
		return $this->context->locales();
	}

	/** @return array<string, mixed> */
	private function row(string $table, string $uid): array
	{
		$row = $this->db()->execute(
			"SELECT t.* FROM cms.{$table} t JOIN cms.nodes n ON n.node = t.node WHERE n.uid = :uid",
			['uid' => $uid],
		)->first();
		$this->assertNotNull($row, "No {$table} row for {$uid}");

		return $row;
	}

	private function rows(string $table, string $uid): int
	{
		return (int) $this->db()->execute(
			"SELECT count(*) AS n FROM cms.{$table} t JOIN cms.nodes n ON n.node = t.node WHERE n.uid = :uid",
			['uid' => $uid],
		)->one()['n'];
	}

	private function liveTitle(string $uid): string
	{
		return json_decode((string) $this->row('nodes', $uid)['content'], true)['title']['value']['en'];
	}

	private function draftTitle(string $uid): string
	{
		return json_decode((string) $this->row('drafts', $uid)['content'], true)['title']['value']['en'];
	}

	private function handle(string $uid): ?string
	{
		$row = $this->db()->execute(
			'SELECT h.handle FROM cms.node_handles h JOIN cms.nodes n ON n.node = h.node WHERE n.uid = :uid',
			['uid' => $uid],
		)->first();

		return $row['handle'] ?? null;
	}

	private function activePath(string $uid): ?string
	{
		$row = $this->db()->execute(
			'SELECT p.path FROM cms.url_paths p JOIN cms.nodes n ON n.node = p.node
				WHERE n.uid = :uid AND p.inactive IS NULL',
			['uid' => $uid],
		)->first();

		return $row['path'] ?? null;
	}

	/** @return list<string> */
	private function referenceOwners(string $target): array
	{
		return array_map(
			static fn(array $row): string => (string) $row['owner_type'],
			$this->db()->execute(
				'SELECT owner_type FROM cms.node_references WHERE target_uid = :uid ORDER BY owner_type',
				['uid' => $target],
			)->all(),
		);
	}
}

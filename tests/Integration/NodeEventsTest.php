<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Celema\Core\Exception\HttpBadRequest;
use Cosray\Actor;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Commands\SyncPublications;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Node\PathManager;
use Cosray\Node\Publications;
use Cosray\Node\ReservedPaths;
use Cosray\Node\Store;
use Cosray\Node\Wrapper;
use Cosray\Tests\Fixtures\Node\TestDraftPage;
use Cosray\Tests\Fixtures\Node\TestSubmission;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeEventsTest extends IntegrationTestCase
{
	private Context $context;
	private Cms $cms;
	private Store $store;
	private Services $services;
	private int $pageType;
	private int $recordType;

	protected function setUp(): void
	{
		parent::setUp();
		$this->context = $this->createContext();
		$this->context->container->tag(Bootstrap::NODE_TAG)->add('test-submission', TestSubmission::class);
		$this->services = Services::withDefaults();
		$this->cms = new Cms($this->context, $this->services);
		$this->store = new Store(
			$this->context->db,
			new PathManager(ReservedPaths::fromConfig($this->context->config)),
			$this->services->types,
			$this->cms->nodeFactory()->uid(),
			factory: $this->cms->nodeFactory(),
			cms: $this->cms,
			context: $this->context,
		);
		$this->pageType = $this->createTestType('test-draft-page');
		$this->recordType = $this->createTestType('test-submission');
	}

	public function testEveryActionIsRecordedWithItsActor(): void
	{
		$author = new Actor($this->createTestUser(['uid' => 'events-author']));
		$publisher = new Actor($this->createTestUser(['uid' => 'events-publisher']));
		$this->createTestNode(['uid' => 'events-parent', 'type' => $this->pageType]);
		$this->store->create(
			$this->cms->nodeFactory()->blueprint(TestDraftPage::class, $this->context, $this->cms),
			$this->payload('events-page', 'Created', published: true),
			$this->context->locales(),
			$author,
		);

		$this->store->draft(
			$this->node('events-page'),
			$this->payload('events-page', 'Drafted'),
			$this->context->locales(),
			$author,
		);
		$this->store->discard($this->node('events-page'), $author);
		$this->store->draft(
			$this->node('events-page'),
			$this->payload('events-page', 'Drafted'),
			$this->context->locales(),
			$author,
		);
		$this->store->applyDraft($this->node('events-page'), $this->context->locales(), $publisher);
		$this->store->published($this->node('events-page'), false, $publisher);
		$this->store->hide($this->node('events-page'), true, $publisher);
		$this->store->hide($this->node('events-page'), false, $publisher);
		$this->store->save(
			$this->node('events-page'),
			['parent' => 'events-parent'] + $this->payload('events-page', 'Drafted', published: false),
			$this->context->locales(),
			$author,
		);
		$this->store->delete($this->node('events-page'), $publisher);

		$this->assertSame(
			[
				['created', 'events-author', []],
				['published', 'events-author', []],
				['drafted', 'events-author', []],
				['discarded', 'events-author', []],
				['drafted', 'events-author', []],
				['applied', 'events-publisher', []],
				['unpublished', 'events-publisher', []],
				['hidden', 'events-publisher', []],
				['shown', 'events-publisher', []],
				['moved', 'events-author', ['to' => 'events-parent', 'from' => null]],
				['deleted', 'events-publisher', []],
			],
			array_map(
				static fn(array $event): array => [
					$event['action'],
					$event['actor'],
					json_decode($event['data'], true),
				],
				$this->events('events-page'),
			),
		);
	}

	public function testOnlyTheMainVersionMovesEditedAndTheEditor(): void
	{
		$author = new Actor($this->createTestUser(['uid' => 'dates-author']));
		$publisher = new Actor($this->createTestUser(['uid' => 'dates-publisher']));
		$this->createTestNode(['uid' => 'dates-page', 'type' => $this->pageType, 'published' => false]);
		$this->store->apply(
			$this->node('dates-page'),
			$this->payload('dates-page', 'Applied', published: false),
			$this->context->locales(),
			$author,
		);
		$applied = $this->nodeRow('dates-page');

		$this->store->draft(
			$this->node('dates-page'),
			$this->payload('dates-page', 'Drafted', published: false),
			$this->context->locales(),
			$publisher,
		);
		$this->assertSame($applied['changed'], $this->nodeRow('dates-page')['changed']);

		$this->store->published($this->node('dates-page'), true, $publisher);
		$published = $this->nodeRow('dates-page');

		$this->assertGreaterThan($applied['changed'], $published['changed']);
		$this->assertSame($applied['edited'], $published['edited']);
		$this->assertSame($author->id, $published['editor']);
		$this->assertSame($applied['changed'], $applied['edited']);
		$this->assertSame(array_last($this->events('dates-page'))['at'], $published['changed']);
	}

	public function testAWriteRecordingSeveralActionsDatesThemAlike(): void
	{
		$this->createTestNode(['uid' => 'together-page', 'type' => $this->pageType, 'published' => false]);
		$this->store->apply(
			$this->node('together-page'),
			$this->payload('together-page', 'Applied', published: true),
			$this->context->locales(),
			Actor::system(),
		);

		$events = $this->events('together-page');

		$this->assertSame(['applied', 'published'], array_column($events, 'action'));
		$this->assertSame($events[0]['at'], $events[1]['at']);
		$this->assertSame($events[0]['at'], $this->nodeRow('together-page')['edited']);
	}

	public function testARecordHasNoPublication(): void
	{
		$blueprint = $this->cms->nodeFactory()->blueprint(TestSubmission::class, $this->context, $this->cms);
		$this->store->create(
			$blueprint,
			$this->recordPayload('record-entry', published: false),
			$this->context->locales(),
			Actor::system(),
		);

		$this->assertSame(0, $this->rows('publications', 'record-entry'));
		$this->assertSame(['created'], array_column($this->events('record-entry'), 'action'));

		$refused = [
			'create' => fn() => $this->store->create(
				$this->cms->nodeFactory()->blueprint(TestSubmission::class, $this->context, $this->cms),
				$this->recordPayload('record-published', published: true),
				$this->context->locales(),
				Actor::system(),
			),
			'save' => fn() => $this->store->save(
				$this->node('record-entry'),
				['hidden' => true] + $this->recordPayload('record-entry', published: false),
				$this->context->locales(),
				Actor::system(),
			),
			'apply' => fn() => $this->store->apply(
				$this->node('record-entry'),
				$this->recordPayload('record-entry', published: true),
				$this->context->locales(),
				Actor::system(),
			),
			'published' => fn() => $this->store->published($this->node('record-entry'), true, Actor::system()),
			'hide' => fn() => $this->store->hide($this->node('record-entry'), true, Actor::system()),
		];

		foreach ($refused as $write => $call) {
			try {
				$call();
				$this->fail("{$write}() published a record");
			} catch (HttpBadRequest) {
				$this->addToAssertionCount(1);
			}
		}

		$this->store->published($this->node('record-entry'), false, Actor::system());
		$this->assertSame(0, $this->rows('publications', 'record-entry'));
	}

	public function testPublicationsReportAndSyncMismatches(): void
	{
		$this->createTestNode(['uid' => 'sync-record', 'type' => $this->recordType, 'published' => true]);
		$this->createTestNode(['uid' => 'sync-page', 'type' => $this->pageType, 'published' => null]);
		$this->createTestNode(['uid' => 'sync-kept', 'type' => $this->pageType, 'published' => true]);
		$this->db()->execute(
			"INSERT INTO cms.drafts (node, editor, content) SELECT node, 1, '{}' FROM cms.nodes WHERE uid = 'sync-record'",
		)->run();
		$publications = new Publications($this->db(), $this->services->types, [
			'test-draft-page' => TestDraftPage::class,
			'test-submission' => TestSubmission::class,
		]);

		$this->assertSame(
			[
				'stale' => [['uid' => 'sync-record', 'type' => 'test-submission']],
				'missing' => [['uid' => 'sync-page', 'type' => 'test-draft-page']],
				'drafts' => [['uid' => 'sync-record', 'type' => 'test-submission']],
			],
			$publications->check(),
		);
		$this->assertSame(['deleted' => 1, 'created' => 1], $publications->sync());

		$this->assertSame(0, $this->rows('publications', 'sync-record'));
		$this->assertFalse((bool) $this->publication('sync-page')['published']);
		$this->assertTrue((bool) $this->publication('sync-kept')['published']);
		$this->assertSame([], $publications->check()['stale']);
		$this->assertSame([], $publications->check()['missing']);
	}

	public function testANodeWithoutPublicationReadsAsNeitherPublishedNorHidden(): void
	{
		$this->createTestNode(['uid' => 'finder-record', 'type' => $this->recordType, 'published' => null]);
		$this->createTestNode(['uid' => 'finder-offline', 'type' => $this->pageType, 'published' => false]);
		$this->createTestNode(['uid' => 'finder-online', 'type' => $this->pageType, 'published' => true]);
		$uids = static fn(iterable $nodes): array => array_map(
			static fn(object $node): string => $node->meta->uid,
			[...$nodes],
		);
		$nodes = fn() => $this->cms->nodes()->types('test-submission', 'test-draft-page')->order('uid');

		$this->assertSame(['finder-offline', 'finder-record'], $uids($nodes()->published(false)));
		$this->assertSame(['finder-online'], $uids($nodes()->published(true)));
		$this->assertSame(
			['finder-offline', 'finder-record'],
			$uids($nodes()->published(null)->filter('published = false')),
		);
		$this->assertSame([], $uids($nodes()->published(null)->hidden(true)));
		$this->assertSame(['finder-offline'], $uids($nodes()->published(null)->stage('unpublished', 1)));
		$this->assertNull($this->cms->node->byUid('finder-record', published: null)?->meta->published);
	}

	public function testTheCommandChecksUntilToldToApply(): void
	{
		$this->createTestNode(['uid' => 'command-record', 'type' => $this->recordType, 'published' => false]);
		$command = new SyncPublications(new Publications($this->db(), $this->services->types, [
			'test-submission' => TestSubmission::class,
		]));
		$run = static function (bool $apply) use ($command): array {
			$buffer = new Buffer();

			return [$command(new Io($buffer), apply: $apply), $buffer->output()];
		};

		[$checked, $report] = $run(false);
		$this->assertSame(1, $checked);
		$this->assertStringContainsString(
			'command-record (test-submission) is a record with a publication row',
			$report,
		);
		$this->assertSame(1, $this->rows('publications', 'command-record'));

		$this->assertSame(0, $run(true)[0]);
		$this->assertSame([0, "The publication rows match the node types.\n"], $run(false));
	}

	/** @return array<string, mixed> */
	private function payload(string $uid, string $title, bool $published = true): array
	{
		return [
			'uid' => $uid,
			'published' => $published,
			'hidden' => false,
			'locked' => false,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
		];
	}

	/** @return array<string, mixed> */
	private function recordPayload(string $uid, bool $published): array
	{
		return [
			'uid' => $uid,
			'published' => $published,
			'hidden' => false,
			'locked' => false,
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Entry']]],
		];
	}

	private function node(string $uid): object
	{
		$node = $this->cms->node->byUid($uid, published: null);
		$this->assertNotNull($node);

		return Wrapper::unwrap($node);
	}

	/** @return array<string, mixed> */
	private function nodeRow(string $uid): array
	{
		return $this->db()->execute('SELECT * FROM cms.nodes WHERE uid = :uid', ['uid' => $uid])->one();
	}

	/** @return array<string, mixed> */
	private function publication(string $uid): array
	{
		return $this->db()->execute(
			'SELECT p.* FROM cms.publications p JOIN cms.nodes n ON n.node = p.node WHERE n.uid = :uid',
			['uid' => $uid],
		)->one();
	}

	private function rows(string $table, string $uid): int
	{
		return (int) $this->db()->execute(
			"SELECT count(*) AS n FROM cms.{$table} t JOIN cms.nodes n ON n.node = t.node WHERE n.uid = :uid",
			['uid' => $uid],
		)->one()['n'];
	}

	/** @return list<array<string, mixed>> */
	private function events(string $uid): array
	{
		return $this->db()->execute(
			'SELECT e.action, u.uid AS actor, e.data, e.at FROM cms.events e
				JOIN cms.nodes n ON n.node = e.node JOIN cms.users u ON u.usr = e.actor
				WHERE n.uid = :uid ORDER BY e.event',
			['uid' => $uid],
		)->all();
	}
}

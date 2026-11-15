<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Core\Exception\HttpBadRequest;
use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Migration\Content;
use Cosray\Node\Edits;
use Cosray\Node\PathManager;
use Cosray\Node\ReservedPaths;
use Cosray\Node\Store;
use Cosray\Node\Wrapper;
use Cosray\Tests\Fixtures\Node\TestDocument;
use Cosray\Tests\IntegrationTestCase;
use Throwable;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeRevisionsTest extends IntegrationTestCase
{
	private Context $context;
	private Cms $cms;
	private Store $store;

	protected function setUp(): void
	{
		parent::setUp();
		$this->context = $this->createContext();
		$services = Services::withDefaults();
		$this->cms = new Cms($this->context, $services);
		$this->store = new Store(
			$this->context->db,
			new PathManager(ReservedPaths::fromConfig($this->context->config)),
			$services->types,
			$this->cms->nodeFactory()->uid(),
			factory: $this->cms->nodeFactory(),
			cms: $this->cms,
			context: $this->context,
		);
		$this->createTestType('test-document');
	}

	public function testEveryMainVersionIsARevisionBasedOnTheOneBefore(): void
	{
		$author = new Actor($this->createTestUser(['uid' => 'revisions-author']));
		$this->store->create(
			$this->cms->nodeFactory()->blueprint(TestDocument::class, $this->context, $this->cms),
			$this->payload('revisions-saved', 'First'),
			$this->context->locales(),
			$author,
		);
		$first = $this->mainRevision('revisions-saved');

		$this->store->save(
			$this->node('revisions-saved'),
			$this->payload('revisions-saved', 'Second'),
			$this->context->locales(),
			Actor::system(),
		);
		$second = $this->mainRevision('revisions-saved');
		$this->store->save(
			$this->node('revisions-saved'),
			$this->payload('revisions-saved', 'Second'),
			$this->context->locales(),
			Actor::system(),
		);

		$this->assertSame($author->id, $first['editor']);
		$this->assertSame(1, $second['editor']);
		$this->assertSame($first['revision'], $second['base']);
		$this->assertSame('Second', json_decode((string) $second['content'], true)['title']['value']['zxx']);
		$this->assertSame($second, $this->mainRevision('revisions-saved'), 'an unchanged save writes no revision');
		$this->assertSame(
			[['created', $first['revision']], ['published', $first['revision']], ['saved', $second['revision']]],
			$this->events('revisions-saved'),
		);
		$this->assertSame('revisions-author', $this->node('revisions-saved', wrapped: true)->meta->get('creator_uid'));
	}

	public function testApplyingAWorkingCopyAsItIsMakesItsRevisionMain(): void
	{
		$this->document('revisions-applied', 'Live');
		$this->store->draft(
			$this->node('revisions-applied'),
			$this->payload('revisions-applied', 'Drafted'),
			$this->context->locales(),
			Actor::system(),
		);
		$drafted = $this->draftRevision('revisions-applied');

		$this->store->applyDraft($this->node('revisions-applied'), $this->context->locales(), Actor::system());

		$this->assertSame($drafted, $this->mainRevision('revisions-applied')['revision']);
		$this->assertSame(['applied', $drafted], array_last($this->events('revisions-applied')));
	}

	public function testTheMainVersionNamesItsAuthorAndWhoMadeItMain(): void
	{
		$author = new Actor($this->createTestUser(['uid' => 'revisions-writer', 'data' => ['name' => 'Writer']]));
		$publisher = new Actor($this->createTestUser([
			'uid' => 'revisions-publisher',
			'data' => ['name' => 'Publisher'],
		]));
		$this->document('revisions-named', 'Live');
		$this->store->draft(
			$this->node('revisions-named'),
			$this->payload('revisions-named', 'Drafted'),
			$this->context->locales(),
			$author,
		);
		$this->store->applyDraft($this->node('revisions-named'), $this->context->locales(), $publisher);
		$this->store->published($this->node('revisions-named'), false, Actor::system());

		$meta = $this->node('revisions-named', wrapped: true)->meta;

		$this->assertSame('revisions-writer', $meta->get('editor_uid'));
		$this->assertSame('Publisher', $meta->get('applier_name'));
	}

	public function testApplyingOtherEditsWritesARevisionBasedOnTheWorkingCopy(): void
	{
		$this->document('revisions-changed', 'Live');
		$this->store->draft(
			$this->node('revisions-changed'),
			$this->payload('revisions-changed', 'Drafted'),
			$this->context->locales(),
			Actor::system(),
		);
		$drafted = $this->draftRevision('revisions-changed');

		$this->store->apply(
			$this->node('revisions-changed'),
			$this->payload('revisions-changed', 'Changed again'),
			$this->context->locales(),
			Actor::system(),
		);
		$main = $this->mainRevision('revisions-changed');

		$this->assertNotSame($drafted, $main['revision']);
		$this->assertSame($drafted, $main['base']);
		$this->assertSame('Changed again', json_decode((string) $main['content'], true)['title']['value']['zxx']);
		$this->assertNull($this->draftRevision('revisions-changed'));
	}

	public function testAWorkingCopyKnowsTheMainRevisionItIsUpToDateWith(): void
	{
		$this->document('revisions-stale', 'Live');
		$main = $this->mainRevision('revisions-stale')['revision'];
		$this->store->draft(
			$this->node('revisions-stale'),
			$this->payload('revisions-stale', 'Drafted'),
			$this->context->locales(),
			Actor::system(),
		);
		$this->store->draft(
			$this->node('revisions-stale'),
			$this->payload('revisions-stale', 'Drafted again'),
			$this->context->locales(),
			Actor::system(),
		);

		$this->assertSame($main, $this->node('revisions-stale', wrapped: true)->meta->get('draft')['base']);

		$this->store->save(
			$this->node('revisions-stale'),
			$this->payload('revisions-stale', 'Saved by the API'),
			$this->context->locales(),
			Actor::system(),
		);
		$draft = $this->node('revisions-stale', wrapped: true)->meta->get('draft');

		$this->assertNotSame($this->mainRevision('revisions-stale')['revision'], $draft['base'], 'stale');

		$this->store->draft(
			$this->node('revisions-stale'),
			$this->payload('revisions-stale', 'Patched too'),
			$this->context->locales(),
			Actor::system(),
			rebase: true,
		);

		$this->assertSame(
			$this->mainRevision('revisions-stale')['revision'],
			$this->node('revisions-stale', wrapped: true)->meta->get('draft')['base'],
		);
	}

	public function testRevertingCopiesARevisionIntoTheWorkingCopy(): void
	{
		$this->document('revisions-reverted', 'First');
		$first = $this->mainRevision('revisions-reverted')['revision'];
		$this->store->apply(
			$this->node('revisions-reverted'),
			$this->payload('revisions-reverted', 'Second'),
			$this->context->locales(),
			Actor::system(),
		);

		$this->store->revert($this->node('revisions-reverted'), $first, $this->context->locales(), Actor::system());

		$this->assertSame('First', $this->cms->node->working('revisions-reverted')?->title());
		$this->assertSame('Second', $this->node('revisions-reverted', wrapped: true)->title());
		$this->assertEquals(
			['source' => 'revert', 'of' => $first],
			json_decode(
				(string) $this->db()->execute(
					"SELECT e.data FROM cms.events e JOIN cms.nodes n ON n.node = e.node
					WHERE n.uid = 'revisions-reverted' AND e.action = 'drafted'",
				)->one()['data'],
				true,
			),
		);

		$this->expectException(HttpBadRequest::class);
		$this->document('revisions-other', 'Other');
		$this->store->revert($this->node('revisions-other'), $first, $this->context->locales(), Actor::system());
	}

	public function testEditsTheUserKeepsGoOnFromTheMainVersionAfterADiscard(): void
	{
		$this->document('revisions-kept', 'Live');
		$main = $this->mainRevision('revisions-kept')['revision'];
		$this->store->draft(
			$this->node('revisions-kept'),
			$this->payload('revisions-kept', 'Drafted'),
			$this->context->locales(),
			Actor::system(),
		);
		$edits = new Edits($this->db());
		$nodeId = (int) $this->mainRevision('revisions-kept')['node'];
		$edits->save($nodeId, 1, $this->draftRevision('revisions-kept'), ['title' => []], []);

		$dropped = $this->store->discard($this->node('revisions-kept'), Actor::system());
		$this->assertNotNull($dropped);
		$edits->rebase($nodeId, 1, $dropped, $main);

		$this->assertSame($main, $edits->get($nodeId, 1)['base'] ?? null);
	}

	public function testANodeMustHoldTheContentOfItsMainRevision(): void
	{
		$this->document('revisions-guarded', 'Live');
		$this->db()->execute('SAVEPOINT guarded')->run();

		try {
			$this->db()->execute(
				"UPDATE cms.nodes SET content = '{}'::jsonb WHERE uid = 'revisions-guarded'",
			)->run();
			$this->db()->execute('SET CONSTRAINTS ALL IMMEDIATE')->run();
			$this->fail('A node held other content than its main revision');
		} catch (Throwable $e) {
			$this->assertStringContainsString('does not hold the content of its main revision', $e->getMessage());
		} finally {
			$this->db()->execute('ROLLBACK TO SAVEPOINT guarded')->run();
			$this->db()->execute('SET CONSTRAINTS ALL DEFERRED')->run();
		}
	}

	public function testAContentMigrationRewritesEveryStoredState(): void
	{
		$this->document('revisions-migrated', 'Live');
		$this->store->apply(
			$this->node('revisions-migrated'),
			$this->payload('revisions-migrated', 'Applied'),
			$this->context->locales(),
			Actor::system(),
		);
		$this->store->draft(
			$this->node('revisions-migrated'),
			$this->payload('revisions-migrated', 'Drafted'),
			$this->context->locales(),
			Actor::system(),
		);
		$nodeId = (int) $this->mainRevision('revisions-migrated')['node'];
		new Edits($this->db())->save($nodeId, 1, null, $this->payload('revisions-migrated', 'Mine')['content'], []);
		$changed = $this->nodeRow('revisions-migrated')['changed'];

		$count = new Content($this->db())->rewrite(
			static function (array $content, string $type): array {
				$content['title']['value']['zxx'] = strtoupper((string) $content['title']['value']['zxx']);

				return $content;
			},
			['test-document'],
		);

		$this->assertSame(4, $count);
		$this->assertSame(
			['LIVE', 'APPLIED', 'DRAFTED'],
			array_map(
				static fn(array $row): string => json_decode((string) $row['content'], true)['title']['value']['zxx'],
				$this->db()->execute(
					'SELECT content FROM cms.revisions WHERE node = :node ORDER BY revision',
					['node' => $nodeId],
				)->all(),
			),
		);
		$this->assertSame(
			'APPLIED',
			json_decode((string) $this->nodeRow('revisions-migrated')['content'], true)['title']['value']['zxx'],
		);
		$this->assertSame('MINE', new Edits($this->db())->get($nodeId, 1)['content']['title']['value']['zxx'] ?? null);
		$this->assertSame($changed, $this->nodeRow('revisions-migrated')['changed']);
		$this->assertSame(0, new Content($this->db())->rewrite(static fn(array $content): ?array => null));
	}

	private function document(string $uid, string $title): void
	{
		$this->createTestNode([
			'uid' => $uid,
			'type' => (int) $this->db()->execute(
				"SELECT type FROM cms.types WHERE handle = 'test-document'",
			)->one()['type'],
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => $title]]],
		]);
	}

	/** @return array<string, mixed> */
	private function payload(string $uid, string $title): array
	{
		return [
			'uid' => $uid,
			'published' => true,
			'hidden' => false,
			'locked' => false,
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => $title]]],
		];
	}

	private function node(string $uid, bool $wrapped = false): object
	{
		$node = $this->cms->node->byUid($uid, published: null);
		$this->assertNotNull($node);

		return $wrapped ? $node : Wrapper::unwrap($node);
	}

	/** @return array<string, mixed> */
	private function nodeRow(string $uid): array
	{
		return $this->db()->execute('SELECT * FROM cms.nodes WHERE uid = :uid', ['uid' => $uid])->one();
	}

	/** @return array<string, mixed> */
	private function mainRevision(string $uid): array
	{
		return $this->db()->execute(
			'SELECT r.* FROM cms.revisions r JOIN cms.nodes n ON n.revision = r.revision WHERE n.uid = :uid',
			['uid' => $uid],
		)->one();
	}

	private function draftRevision(string $uid): ?int
	{
		$row = $this->db()->execute(
			'SELECT d.revision FROM cms.drafts d JOIN cms.nodes n ON n.node = d.node WHERE n.uid = :uid',
			['uid' => $uid],
		)->first();

		return $row ? (int) $row['revision'] : null;
	}

	/** @return list<array{string, ?int}> */
	private function events(string $uid): array
	{
		return array_map(
			static fn(array $row): array => [(string) $row['action'], $row['revision']],
			$this->db()->execute(
				'SELECT e.action, e.revision FROM cms.events e JOIN cms.nodes n ON n.node = e.node
					WHERE n.uid = :uid ORDER BY e.event',
				['uid' => $uid],
			)->all(),
		);
	}
}

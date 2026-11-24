<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Contract\NodeListener;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Node\Listeners;
use Cosray\Node\Store;
use Cosray\Node\Wrapper;
use Cosray\Node\Write;
use Cosray\Tests\Fixtures\Node\TestDraftPage;
use Cosray\Tests\Fixtures\Plugin\TestWriteLog;
use Cosray\Tests\IntegrationTestCase;
use stdClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeListenersTest extends IntegrationTestCase
{
	private Context $context;
	private Cms $cms;
	private Services $services;
	private Listeners $listeners;

	protected function setUp(): void
	{
		parent::setUp();
		$this->context = $this->createContext();
		$this->services = Services::withDefaults();
		$this->cms = new Cms($this->context, $this->services);
		$this->listeners = new Listeners();
		$this->context->container->add(Listeners::class, $this->listeners)->value();
		$this->createTestType('test-draft-page');
		TestWriteLog::$writes = [];
	}

	public function testAListenerHearsWhatEachWriteDid(): void
	{
		$this->listeners->add(TestWriteLog::class);
		$editor = new Actor($this->createTestUser(['uid' => 'listeners-editor']));
		$store = $this->store();

		$this->create($store, 'heard-parent');
		$this->create($store, 'heard', published: false);
		$store->draft(
			$this->node('heard'),
			$this->payload('heard', 'Drafted', false),
			$this->context->locales(),
			$editor,
		);
		$store->applyDraft($this->node('heard'), $this->context->locales(), $editor, publish: true, cause: [
			'source' => 'test',
		]);
		// Nothing changes, so no listener hears of it.
		$store->published($this->node('heard'), true, $editor);
		$store->save(
			$this->node('heard'),
			['parent' => 'heard-parent'] + $this->payload('heard', 'Drafted', true),
			$this->context->locales(),
			$editor,
		);

		$writes = array_values(array_filter(
			TestWriteLog::$writes,
			static fn(Write $write): bool => $write->uid === 'heard',
		));

		$this->assertSame(
			[['created'], ['drafted'], ['applied', 'published'], ['moved']],
			array_column($writes, 'actions'),
		);
		$this->assertSame('test-draft-page', $writes[0]->type);
		$this->assertSame(Actor::system()->id, $writes[0]->actor->id);
		$this->assertSame($editor->id, $writes[2]->actor->id);
		$this->assertTrue($writes[2]->has('published', 'unpublished'));
		$this->assertFalse($writes[2]->has('deleted'));
		$this->assertSame(['source' => 'test'], $writes[2]->data('published'));
		$this->assertSame(['from' => null, 'to' => 'heard-parent'], $writes[3]->data('moved'));
		$this->assertSame([], $writes[3]->data('published'));
		$this->assertSame($this->lastEventTime('heard'), $writes[3]->at->format('Y-m-d H:i:s.uP'));
	}

	public function testAListenerIsBuiltForTheContextOfTheWrite(): void
	{
		$this->listeners->add(TestWriteLog::class);
		$log = $this->listeners->for($this->context, $this->cms)[0];

		$this->assertInstanceOf(TestWriteLog::class, $log);
		$this->assertSame($this->context, $log->context);
	}

	public function testAListenerTheContainerKnowsIsTakenFromThere(): void
	{
		$known = new TestWriteLog($this->context);
		$this->context->container->add(TestWriteLog::class, $known);
		$this->listeners->add(TestWriteLog::class);

		$this->assertSame([$known], $this->listeners->for($this->context));
	}

	public function testWhatAListenerWritesIsUndoneWithTheWrite(): void
	{
		$db = $this->db();
		$this->listeners->add(new class($db) implements NodeListener {
			public function __construct(
				private readonly \Celema\Quma\Database $db,
			) {}

			public function written(Write $write): void
			{
				$this->db->execute(
					"INSERT INTO cms.jobs (queue, job, payload) VALUES ('listeners', :uid, '{}')",
					['uid' => $write->uid],
				)->run();
			}
		});
		$store = $this->store();

		try {
			$db->transaction(function () use ($store): never {
				$this->create($store, 'undone');

				throw new RuntimeException('The site changed its mind.');
			});
		} catch (RuntimeException $e) {
			$this->assertSame('The site changed its mind.', $e->getMessage());
		}

		$this->create($store, 'kept');

		$this->assertSame(['kept'], array_column(
			$db->execute("SELECT job FROM cms.jobs WHERE queue = 'listeners' ORDER BY id")->all(),
			'job',
		));
	}

	public function testAFailingListenerFailsTheWrite(): void
	{
		$this->listeners->add(new class implements NodeListener {
			public function written(Write $write): void
			{
				throw new RuntimeException('The listener refuses.');
			}
		});

		try {
			$this->create($this->store(), 'refused');
			$this->fail('The write went through');
		} catch (\Throwable $e) {
			$this->assertStringContainsString('The listener refuses.', $e->getMessage());
		}

		$this->assertNull($this->cms->node->byUid('refused', published: null));
	}

	public function testADescendantDeletedWithItsParentIsHeardWithItsOwnType(): void
	{
		$this->listeners->add(TestWriteLog::class);
		$document = $this->createTestType('test-document');
		$store = $this->store();
		$this->create($store, 'family');
		$this->createTestNode(['uid' => 'family-child', 'type' => $document, 'parent' => $this->nodeId('family')]);

		$store->delete($this->node('family'), Actor::system(), withChildren: true);

		$deleted = array_values(array_filter(
			TestWriteLog::$writes,
			static fn(Write $write): bool => $write->has('deleted'),
		));
		$types = array_column($deleted, 'type', 'uid');
		ksort($types);

		$this->assertSame(['family' => 'test-draft-page', 'family-child' => 'test-document'], $types);
	}

	public function testOnlyNodeListenersAreAccepted(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Node listeners must implement ' . NodeListener::class . ': ' . stdClass::class);

		/** @phpstan-ignore argument.type */
		$this->listeners->add(stdClass::class);
	}

	private function store(): Store
	{
		return Store::of($this->context, $this->cms, $this->services->types);
	}

	private function create(Store $store, string $uid, bool $published = true): void
	{
		$store->create(
			$this->cms->nodeFactory()->blueprint(TestDraftPage::class, $this->context, $this->cms),
			$this->payload($uid, 'Created', $published),
			$this->context->locales(),
			Actor::system(),
		);
	}

	/** @return array<string, mixed> */
	private function payload(string $uid, string $title, bool $published): array
	{
		return [
			'uid' => $uid,
			'published' => $published,
			'hidden' => false,
			'locked' => false,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
		];
	}

	private function node(string $uid): object
	{
		$node = $this->cms->node->byUid($uid, published: null);
		$this->assertNotNull($node);

		return Wrapper::unwrap($node);
	}

	private function nodeId(string $uid): int
	{
		return (int) $this->db()->execute('SELECT node FROM cms.nodes WHERE uid = :uid', [
			'uid' => $uid,
		])->one()['node'];
	}

	private function lastEventTime(string $uid): string
	{
		return (string) $this->db()->execute(
			"SELECT to_char(max(e.at), 'YYYY-MM-DD HH24:MI:SS.USTZH:TZM') AS at FROM cms.events e
				JOIN cms.nodes n ON n.node = e.node WHERE n.uid = :uid",
			['uid' => $uid],
		)->one()['at'];
	}
}

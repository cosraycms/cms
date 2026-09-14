<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Args;
use Celema\Console\BufferedIo;
use Cosray\Actor;
use Cosray\Commands\Fulltext;
use Cosray\Exception\RuntimeException;
use Cosray\Fulltext\Rebuild;
use Cosray\Tests\Fixtures\Node\FulltextDocument;
use Cosray\Tests\FulltextTestCase;

final class FulltextStoreTest extends FulltextTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->environment();
	}

	private function rebuild(): Rebuild
	{
		return new Rebuild($this->db(), $this->languages, $this->services, $this->context->container);
	}

	public function testLiveSavesIndexMaterializedTitlesAndSelectedText(): void
	{
		$this->page('fts-live', 'Original title', 'Original body');
		self::assertSame("Original title\n\nOriginal body", $this->source('fts-live'));
		$this->store->save(
			$this->node('fts-live'),
			$this->payload('fts-live', 'New title', 'New body'),
			$this->languages,
			Actor::system(),
		);
		self::assertSame("New title\n\nNew body", $this->source('fts-live'));
		self::assertTrue($this->db()->getConn()->inTransaction());
	}

	public function testUnpublishedHiddenAndNonRoutableContentIsIndexed(): void
	{
		$this->writer->create(
			$this->writer
				->prepare(FulltextDocument::class, ['body' => 'Unpublished internal document'])
				->uid('fts-document')
				->hidden(),
		);
		self::assertSame('Unpublished internal document', $this->source('fts-document'));
	}

	public function testWorkingCopyAndDiscardLeaveTheLiveIndexAlone(): void
	{
		$this->page('fts-draft', 'Live', 'Public prose');
		$this->store->draft(
			$this->node('fts-draft'),
			$this->payload('fts-draft', 'Pending', 'Secret draft'),
			$this->languages,
			Actor::system(),
		);
		self::assertSame("Live\n\nPublic prose", $this->source('fts-draft'));
		$this->store->discard($this->node('fts-draft'));
		self::assertSame("Live\n\nPublic prose", $this->source('fts-draft'));
	}

	public function testPublishingAWorkingCopyReplacesTheIndex(): void
	{
		$this->page('fts-publish', 'Live');
		$this->store->draft(
			$this->node('fts-publish'),
			$this->payload('fts-publish', 'Released', 'New prose'),
			$this->languages,
			Actor::system(),
		);
		self::assertTrue($this->store->publishDraft($this->node('fts-publish'), $this->languages, Actor::system()));
		self::assertSame("Released\n\nNew prose", $this->source('fts-publish'));
	}

	public function testUnpublishingKeepsTheDocumentAndFoldsPendingContent(): void
	{
		$this->page('fts-unpublish', 'Live');
		$this->store->unpublish($this->node('fts-unpublish'), $this->languages, Actor::system());
		self::assertSame('Live', $this->source('fts-unpublish'));
		$this->store->save(
			$this->node('fts-unpublish'),
			$this->payload('fts-unpublish', 'Live again'),
			$this->languages,
			Actor::system(),
		);
		$this->store->draft(
			$this->node('fts-unpublish'),
			$this->payload('fts-unpublish', 'Folded'),
			$this->languages,
			Actor::system(),
		);
		$this->store->unpublish($this->node('fts-unpublish'), $this->languages, Actor::system());
		self::assertSame('Folded', $this->source('fts-unpublish'));
		self::assertFalse($this->sql('node', ['uid' => 'fts-unpublish'])->one()['published']);
	}

	public function testDeleteRemovesAllLocaleDocuments(): void
	{
		$this->page('fts-delete', 'Live');
		$id = (int) $this->sql('node', ['uid' => 'fts-delete'])->one()['node'];
		self::assertCount(2, $this->sql('inspect', ['node' => $id])->all());
		$this->store->delete($this->node('fts-delete'), Actor::system());
		self::assertSame([], $this->sql('inspect', ['node' => $id])->all());
	}

	public function testIndexingFailureRollsBackTheEditorialWriteEvenInsideAnOuterTransaction(): void
	{
		$this->page('fts-rollback', 'Keep title', 'Keep body');
		$before = $this->sql('node', ['uid' => 'fts-rollback'])->one();
		$this->languages->add('bad', 'Bad analyzer', pgDict: 'unavailable');
		try {
			$this->store->save(
				$this->node('fts-rollback'),
				$this->payload('fts-rollback', 'Rejected', 'Must not persist'),
				$this->languages,
				Actor::system(),
			);
			self::fail('Expected indexing to fail.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('unavailable', $e->getMessage());
		}
		self::assertSame($before, $this->sql('node', ['uid' => 'fts-rollback'])->one());
		self::assertSame("Keep title\n\nKeep body", $this->source('fts-rollback'));
		self::assertTrue($this->db()->getConn()->inTransaction());
	}

	public function testRebuildMatchesSavesAndDoesNotChangeContentHistoryOrTimestamps(): void
	{
		$this->page('fts-rebuild', 'Stored title', 'Live body');
		$this->store->draft(
			$this->node('fts-rebuild'),
			$this->payload('fts-rebuild', 'Private working title'),
			$this->languages,
			Actor::system(),
		);
		$before = $this->sql('node', ['uid' => 'fts-rebuild'])->one();
		$documents = $this->sql('inspect', ['node' => $before['node']])->all();
		$this->sql('clear')->run();
		$report = $this->rebuild()->run();
		self::assertSame(
			['processed' => 1, 'indexed' => 1, 'empty' => 0, 'failed' => 0, 'missingTitles' => 0],
			$report,
		);
		self::assertSame($documents, $this->sql('inspect', ['node' => $before['node']])->all());
		self::assertSame($before, $this->sql('node', ['uid' => 'fts-rebuild'])->one());
		self::assertSame($report, $this->rebuild()->run());
	}

	public function testRebuildReadsTheStoredTitleInsteadOfHydratingOrRecomputing(): void
	{
		$this->page('fts-title-map', 'Runtime title', 'Body');
		$this->sql('storedTitle', ['uid' => 'fts-title-map', 'title' => '{"en":"Materialized title"}'])->run();
		$this->rebuild()->run();
		self::assertSame("Materialized title\n\nBody", $this->source('fts-title-map'));
		$this->sql('storedTitle', ['uid' => 'fts-title-map', 'title' => '{}'])->run();
		self::assertSame(2, $this->rebuild()->run()['missingTitles']);
		self::assertSame('Body', $this->source('fts-title-map'));
	}

	public function testUnknownSchemasAreReportedAndDoNotPreventOtherNodesFromRebuilding(): void
	{
		$type = $this->createTestType('fts-missing-schema');
		$this->createTestNode(['uid' => 'fts-unknown', 'type' => $type]);
		$this->page('fts-known', 'Known');
		$this->sql('clear')->run();
		$errors = [];
		$report = $this->rebuild()->run(static function (string $uid, \Throwable $error) use (&$errors): void {
			$errors[$uid] = $error->getMessage();
		});
		self::assertSame(1, $report['failed']);
		self::assertSame(1, $report['indexed']);
		self::assertSame(['fts-unknown'], array_keys($errors));
		self::assertSame('Known', $this->source('fts-known'));
		self::assertSame(1, (new Fulltext($this->rebuild()))(new Args(), new BufferedIo()));
	}
}

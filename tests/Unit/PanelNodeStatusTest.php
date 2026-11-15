<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\NodeStatus;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelNodeStatusTest extends TestCase
{
	private const string CHANGED = '2026-11-10 12:00:00+00';
	private const string AFTER = '2026-11-10 13:00:00+00';

	/** @return iterable<string, array{array<string, mixed>, string, bool, string}> */
	public static function combinations(): iterable
	{
		// Revision 1 is the main version, 2 the draft.
		$draft = ['changed' => self::CHANGED, 'editorName' => 'Ada', 'revision' => 2];
		$mine = ['changed' => self::AFTER, 'base' => 2];
		$stale = ['changed' => self::AFTER, 'base' => 1];

		yield 'published, nothing pending' => [['published' => true], 'published', false, 'none'];
		yield 'published with draft and mine' => [
			['published' => true, 'draft' => $draft, 'autosave' => $mine],
			'published',
			true,
			'present',
		];
		yield 'unpublished, just created' => [['published' => false], 'unpublished', false, 'none'];
		yield 'unpublished with draft' => [['published' => false, 'draft' => $draft], 'unpublished', true, 'none'];
		yield 'unpublished with mine' => [
			['published' => false, 'autosave' => ['changed' => self::AFTER, 'base' => 1]],
			'unpublished',
			false,
			'present',
		];
		yield 'mine older than the draft' => [
			['published' => true, 'draft' => $draft, 'autosave' => $stale],
			'published',
			true,
			'conflict',
		];
		yield 'mine older than the main version' => [
			['published' => true, 'autosave' => ['changed' => self::AFTER, 'base' => 0]],
			'published',
			false,
			'conflict',
		];
		yield 'mine from an unknown state' => [
			['published' => true, 'autosave' => ['changed' => self::AFTER, 'base' => null]],
			'published',
			false,
			'conflict',
		];
	}

	/** @param array<string, mixed> $facts */
	#[DataProvider('combinations')]
	public function testDerivesTheStages(array $facts, string $main, bool $draft, string $mine): void
	{
		$status = $this->derive($facts);

		$this->assertTrue($status->publication);
		$this->assertSame($main, $status->main);
		$this->assertSame($draft, $status->draft);
		$this->assertSame($mine, $status->mine);
		$this->assertSame(['main', 'draft', 'mine'], array_column($status->stages($this->date(...)), 'key'));
	}

	public function testTheDraftStageNamesWhoSavedItAndWhen(): void
	{
		$status = $this->derive(['published' => true, 'draft' => ['changed' => self::AFTER, 'editorName' => 'Grace']]);

		$this->assertSame(self::AFTER, $status->saved);
		$this->assertSame('Grace', $status->savedBy);
		$this->assertSame(self::CHANGED, $status->edited);
	}

	public function testARecordHasTheStoredStateAndTheUsersEdits(): void
	{
		$status = $this->derive([
			'record' => true,
			'published' => true,
			'autosave' => ['changed' => self::AFTER, 'base' => 1],
		]);

		$this->assertFalse($status->publication);
		$this->assertFalse($status->draft);
		$this->assertSame('present', $status->mine);
		$this->assertSame(['saved', 'mine'], array_column($status->stages($this->date(...)), 'key'));
	}

	public function testOnlyTheMainVersionHasABadgeSayingWhetherVisitorsSeeIt(): void
	{
		$online = $this->derive(['published' => true])->stages($this->date(...));
		$offline = $this->derive(['published' => false])->stages($this->date(...));

		$this->assertNotSame('', $online[0]['badge']);
		$this->assertNotSame($online[0]['badge'], $offline[0]['badge']);
		$this->assertSame(['', ''], [$online[1]['badge'], $online[2]['badge']]);
	}

	public function testATypeWithoutDraftsSavesToItsMainVersion(): void
	{
		$status = $this->derive([
			'drafts' => false,
			'published' => false,
			'routable' => false,
			'editor' => 'Ada',
			'draft' => ['changed' => self::CHANGED, 'editorName' => 'Grace', 'revision' => 2],
			'autosave' => ['changed' => self::AFTER, 'base' => 1],
		]);
		$stages = $status->stages($this->date(...));

		$this->assertSame(['main', 'mine'], array_column($stages, 'key'));
		$this->assertFalse($status->draft, 'a working copy left over is not shown');
		$this->assertSame('present', $status->mine);
		$this->assertSame('status:inactive', $stages[0]['badge']);
		$this->assertSame('Ada', $status->savedBy);
	}

	public function testTheSentenceNamesEveryStageWithItsBadge(): void
	{
		$sentence = NodeStatus::sentence([
			['name' => 'Main version', 'text' => 'applied', 'badge' => 'Online'],
			['name' => 'Draft', 'text' => 'none', 'badge' => ''],
		]);

		$this->assertSame('Main version (Online) – applied; Draft – none', $sentence);
	}

	public function testTheSentenceNamesEveryStage(): void
	{
		$sentence = NodeStatus::sentence([
			['name' => 'Main version', 'text' => 'published'],
			['name' => 'Draft', 'text' => 'none'],
		]);

		$this->assertSame('Main version – published; Draft – none', $sentence);
	}

	/** @param array<string, mixed> $facts */
	private function derive(array $facts): NodeStatus
	{
		return NodeStatus::of(
			drafts: !($facts['record'] ?? false) && ($facts['drafts'] ?? true),
			publication: !($facts['record'] ?? false),
			published: (bool) ($facts['published'] ?? false),
			edited: self::CHANGED,
			editor: $facts['editor'] ?? null,
			draft: $facts['draft'] ?? null,
			autosave: $facts['autosave'] ?? null,
			revision: 1,
			routable: $facts['routable'] ?? true,
		);
	}

	private function date(string $value, bool $time): string
	{
		return $value;
	}
}

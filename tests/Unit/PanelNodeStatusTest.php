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
	private const string BEFORE = '2026-11-10 10:00:00+00';
	private const string CHANGED = '2026-11-10 12:00:00+00';
	private const string AFTER = '2026-11-10 13:00:00+00';

	/** @return iterable<string, array{array<string, mixed>, string, bool, string}> */
	public static function combinations(): iterable
	{
		$draft = ['changed' => self::CHANGED, 'editorName' => 'Ada'];
		$mine = ['changed' => self::AFTER, 'base' => self::CHANGED];
		$stale = ['changed' => self::AFTER, 'base' => self::BEFORE];

		yield 'published, nothing pending' => [['published' => true], 'published', false, 'none'];
		yield 'published with draft and mine' => [
			['published' => true, 'draft' => $draft, 'autosave' => $mine],
			'published',
			true,
			'present',
		];
		yield 'unpublished, just created' => [['published' => false], 'unpublished', false, 'none'];
		yield 'unpublished with draft' => [['published' => false, 'draft' => $draft], 'unpublished', true, 'none'];
		yield 'unpublished with mine' => [['published' => false, 'autosave' => $mine], 'unpublished', false, 'present'];
		yield 'mine older than the draft' => [
			['published' => true, 'draft' => $draft, 'autosave' => $stale],
			'published',
			true,
			'conflict',
		];
		yield 'mine older than the main version' => [
			['published' => true, 'autosave' => $stale],
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

		$this->assertFalse($status->record);
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
		$this->assertSame(self::CHANGED, $status->changed);
	}

	public function testARecordHasTheStoredStateAndTheUsersEdits(): void
	{
		$status = $this->derive([
			'record' => true,
			'published' => true,
			'autosave' => ['changed' => self::AFTER, 'base' => self::CHANGED],
		]);

		$this->assertTrue($status->record);
		$this->assertFalse($status->draft);
		$this->assertSame('present', $status->mine);
		$this->assertSame(['saved', 'mine'], array_column($status->stages($this->date(...)), 'key'));
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
			record: (bool) ($facts['record'] ?? false),
			published: (bool) ($facts['published'] ?? false),
			changed: self::CHANGED,
			editor: $facts['editor'] ?? null,
			draft: $facts['draft'] ?? null,
			autosave: $facts['autosave'] ?? null,
		);
	}

	private function date(string $value, bool $time): string
	{
		return $value;
	}
}

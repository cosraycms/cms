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

		yield 'online, nothing pending' => [['published' => true, 'released' => self::BEFORE], 'online', false, 'none'];
		yield 'online with draft and mine' => [
			['published' => true, 'released' => self::BEFORE, 'draft' => $draft, 'autosave' => $mine],
			'online',
			true,
			'present',
		];
		yield 'offline with draft' => [
			['published' => false, 'released' => self::BEFORE, 'draft' => $draft],
			'offline',
			true,
			'none',
		];
		yield 'never published' => [['published' => false, 'released' => null], 'none', true, 'none'];
		yield 'never published with mine' => [
			['published' => false, 'released' => null, 'autosave' => $mine],
			'none',
			true,
			'present',
		];
		yield 'mine older than the draft' => [
			['published' => true, 'released' => self::BEFORE, 'draft' => $draft, 'autosave' => $stale],
			'online',
			true,
			'conflict',
		];
		yield 'mine older than the node' => [
			['published' => true, 'released' => self::BEFORE, 'autosave' => $stale],
			'online',
			false,
			'conflict',
		];
	}

	/** @param array<string, mixed> $facts */
	#[DataProvider('combinations')]
	public function testDerivesTheStages(array $facts, string $website, bool $draft, string $mine): void
	{
		$status = $this->derive($facts);

		$this->assertFalse($status->record);
		$this->assertSame($website, $status->website);
		$this->assertSame($draft, $status->draft);
		$this->assertSame($mine, $status->mine);
		$this->assertSame(['website', 'draft', 'mine'], array_column($status->stages($this->date(...)), 'key'));
	}

	public function testANeverPublishedNodeShowsItsOwnSaveAsTheDraft(): void
	{
		$status = $this->derive(['published' => false, 'released' => null, 'editor' => 'Grace']);

		$this->assertSame(self::CHANGED, $status->saved);
		$this->assertSame('Grace', $status->savedBy);
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
			['name' => 'Website', 'text' => 'online'],
			['name' => 'Draft', 'text' => 'none'],
		]);

		$this->assertSame('Website – online; Draft – none', $sentence);
	}

	/** @param array<string, mixed> $facts */
	private function derive(array $facts): NodeStatus
	{
		return NodeStatus::of(
			record: (bool) ($facts['record'] ?? false),
			published: (bool) ($facts['published'] ?? false),
			released: $facts['released'] ?? null,
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

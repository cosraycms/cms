<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\Image;
use Cosray\Field\Number;
use Cosray\Field\RichText;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Panel\EntrySummary;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EntrySummaryTest extends TestCase
{
	private const array ASSETS = [
		'img-1' => ['url' => '/media/img-1.jpg', 'thumbUrl' => '/media/img-1/thumb.jpg'],
		'file-1' => ['url' => '/media/file-1.pdf'],
	];

	#[Test]
	public function primarySecondaryAndThumbFollowDeclarationOrder(): void
	{
		$summary = EntrySummary::of(
			$this->type([
				['name' => 'photo', 'type' => Image::class],
				['name' => 'name', 'type' => Text::class],
				['name' => 'role', 'type' => Text::class],
				['name' => 'bio', 'type' => Textarea::class],
			]),
			[
				'photo' => ['value' => ['zxx' => [['uid' => 'img-1']]]],
				'name' => ['value' => ['zxx' => 'Anja Reinhardt']],
				'role' => ['value' => ['zxx' => 'Gruppenleitung']],
				'bio' => ['value' => ['zxx' => 'Never shown']],
			],
			self::ASSETS,
			'de',
		);

		$this->assertSame('Anja Reinhardt', $summary->primary);
		$this->assertSame('Gruppenleitung', $summary->secondary);
		$this->assertSame('/media/img-1/thumb.jpg', $summary->thumb);
		$this->assertTrue($summary->hasImage);
		$this->assertSame('name', $summary->primaryField);
		$this->assertSame('role', $summary->secondaryField);
	}

	#[Test]
	public function emptyFieldsAreSkipped(): void
	{
		$summary = EntrySummary::of(
			$this->type([
				['name' => 'a', 'type' => Text::class],
				['name' => 'b', 'type' => Text::class],
				['name' => 'c', 'type' => Number::class],
			]),
			[
				'a' => ['value' => ['zxx' => '   ']],
				'c' => ['value' => ['zxx' => 42]],
			],
			[],
			'de',
		);

		$this->assertSame('42', $summary->primary);
		$this->assertSame('c', $summary->primaryField);
		$this->assertNull($summary->secondary);
		$this->assertNull($summary->secondaryField);
		$this->assertNull($summary->thumb);
		$this->assertFalse($summary->hasImage);
	}

	#[Test]
	public function richtextIsFlattenedToPlainText(): void
	{
		$doc = [
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [
						['type' => 'text', 'text' => 'Bring '],
						['type' => 'text', 'text' => 'boots', 'marks' => [['type' => 'bold']]],
						['type' => 'hardBreak'],
						['type' => 'text', 'text' => ' and a hat.'],
					],
				],
				[
					'type' => 'paragraph',
					'content' => [['type' => 'text', 'text' => 'Second paragraph.']],
				],
			],
		];
		$summary = EntrySummary::of(
			$this->type([
				['name' => 'time', 'type' => Text::class],
				['name' => 'description', 'type' => RichText::class],
			]),
			[
				'time' => ['value' => ['zxx' => '08:00']],
				'description' => ['value' => ['zxx' => $doc]],
			],
			[],
			'de',
		);

		$this->assertSame('08:00', $summary->primary);
		$this->assertSame('Bring boots and a hat. Second paragraph.', $summary->secondary);
	}

	#[Test]
	public function longTextIsTruncated(): void
	{
		$summary = EntrySummary::of(
			$this->type([['name' => 'a', 'type' => Text::class]]),
			['a' => ['value' => ['zxx' => str_repeat('word ', 30)]]],
			[],
			'de',
		);

		$this->assertLessThanOrEqual(61, mb_strlen((string) $summary->primary));
		$this->assertStringStartsWith('word word', (string) $summary->primary);
		$this->assertStringEndsWith('…', (string) $summary->primary);
		$this->assertStringEndsNotWith(' …', (string) $summary->primary);
	}

	#[Test]
	public function localeFallsBackToNeutralThenAnyOther(): void
	{
		$type = $this->type([
			['name' => 'a', 'type' => Text::class],
			['name' => 'photo', 'type' => Image::class],
		]);
		$summary = EntrySummary::of(
			$type,
			[
				'a' => ['value' => ['en' => 'English', 'zxx' => 'Neutral']],
				'photo' => ['value' => ['en' => [['uid' => 'img-1']], 'de' => []]],
			],
			self::ASSETS,
			'de',
		);

		$this->assertSame('Neutral', $summary->primary);
		$this->assertSame('/media/img-1/thumb.jpg', $summary->thumb);

		$summary = EntrySummary::of(
			$type,
			['a' => ['value' => ['en' => 'English', 'de' => '']]],
			self::ASSETS,
			'de',
		);

		$this->assertSame('English', $summary->primary);
	}

	#[Test]
	public function thumbFallsBackToTheAssetUrlAndSkipsUnknownAssets(): void
	{
		$type = $this->type([['name' => 'photo', 'type' => Image::class]]);
		$summary = EntrySummary::of(
			$type,
			['photo' => ['value' => ['zxx' => [['uid' => 'missing'], ['uid' => 'file-1']]]]],
			self::ASSETS,
			'de',
		);

		$this->assertSame('/media/file-1.pdf', $summary->thumb);
		$this->assertTrue($summary->hasImage);

		$summary = EntrySummary::of($type, [], self::ASSETS, 'de');

		$this->assertNull($summary->thumb);
		$this->assertTrue($summary->hasImage);
	}

	#[Test]
	public function malformedDataIsIgnored(): void
	{
		$summary = EntrySummary::of(
			[
				'fields' => [
					'not a field',
					['name' => 'photo', 'type' => Image::class],
					['name' => 'text', 'type' => RichText::class],
				],
			],
			[
				'photo' => ['value' => ['zxx' => 'not a list']],
				'text' => [
					'value' => ['zxx' => ['type' => 'doc', 'content' => ['stray', ['type' => 'text', 'text' => 'ok']]]],
				],
			],
			self::ASSETS,
			'de',
		);

		$this->assertNull($summary->thumb);
		$this->assertTrue($summary->hasImage);
		$this->assertSame('ok', $summary->primary);
	}

	/**
	 * @param list<array{name: string, type: string}> $fields
	 * @return array<string, mixed>
	 */
	private function type(array $fields): array
	{
		return ['type' => 'App\\Entry', 'label' => 'Entry', 'fields' => $fields];
	}
}

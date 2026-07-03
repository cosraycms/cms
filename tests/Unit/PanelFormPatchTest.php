<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\FormPatch;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelFormPatchTest extends TestCase
{
	public function testReplacesSubmittedLocalesAndKeepsStoredOnes(): void
	{
		$patch = new FormPatch([
			['name' => 'title', 'type' => 'Text', 'control' => ['name' => 'text', 'props' => []]],
		]);

		$content = $patch->content(
			['title' => ['type' => 'Text', 'value' => ['en' => 'Old', 'fr' => 'Ancien']]],
			['title' => ['value' => ['en' => 'New', 'de' => 'Neu']]],
		);

		$this->assertSame(
			['en' => 'New', 'fr' => 'Ancien', 'de' => 'Neu'],
			$content['title']['value'],
		);
	}

	public function testLeavesUnsubmittedFieldsAndUnknownKeysUntouched(): void
	{
		$patch = new FormPatch([
			['name' => 'title', 'type' => 'Text', 'control' => ['name' => 'text', 'props' => []]],
		]);

		$stored = [
			'title' => ['type' => 'Text', 'value' => ['zxx' => 'Old'], 'meta' => ['x' => 1]],
			'mystery' => ['type' => 'Unknown', 'value' => ['zxx' => 'kept']],
		];
		$content = $patch->content($stored, ['title' => ['value' => ['zxx' => 'New']]]);

		$this->assertSame('kept', $content['mystery']['value']['zxx']);
		$this->assertSame(['x' => 1], $content['title']['meta']);
		$this->assertSame('New', $content['title']['value']['zxx']);
	}

	public function testIgnoresSubmittedFieldsWithoutDescriptor(): void
	{
		$patch = new FormPatch([
			['name' => 'title', 'type' => 'Text', 'control' => ['name' => 'text', 'props' => []]],
		]);

		$content = $patch->content(
			['title' => ['type' => 'Text', 'value' => ['zxx' => 'Old']]],
			['crafted' => ['value' => ['zxx' => 'evil']]],
		);

		$this->assertArrayNotHasKey('crafted', $content);
	}

	public function testCastsCheckboxAndNumberLeaves(): void
	{
		$patch = new FormPatch([
			['name' => 'flag', 'type' => 'Checkbox', 'control' => ['name' => 'checkbox', 'props' => []]],
			['name' => 'count', 'type' => 'Number', 'control' => ['name' => 'number', 'props' => []]],
		]);

		$content = $patch->content(
			[
				'flag' => ['type' => 'Checkbox', 'value' => ['zxx' => true]],
				'count' => ['type' => 'Number', 'value' => ['zxx' => 1]],
			],
			[
				'flag' => ['value' => ['zxx' => '']],
				'count' => ['value' => ['zxx' => '2.5']],
			],
		);

		$this->assertFalse($content['flag']['value']['zxx']);
		$this->assertSame(2.5, $content['count']['value']['zxx']);

		$content = $patch->content($content, [
			'flag' => ['value' => ['zxx' => '1']],
			'count' => ['value' => ['zxx' => '']],
		]);

		$this->assertTrue($content['flag']['value']['zxx']);
		$this->assertNull($content['count']['value']['zxx']);
	}

	public function testGroupReplacesKnownKeysAndKeepsUnknownOnes(): void
	{
		$patch = new FormPatch([
			[
				'name' => 'price',
				'type' => 'Money',
				'control' => [
					'name' => 'group',
					'props' => [
						'fields' => [
							['key' => 'amount', 'control' => ['name' => 'number', 'props' => []]],
							['key' => 'currency', 'control' => ['name' => 'option', 'props' => []]],
						],
					],
				],
			],
		]);

		$content = $patch->content(
			[
				'price' => [
					'type' => 'Money',
					'value' => ['zxx' => ['amount' => 1.0, 'currency' => 'EUR', 'stashed' => 'kept']],
				],
			],
			['price' => ['value' => ['zxx' => ['amount' => '2.5', 'currency' => 'USD']]]],
		);

		$this->assertSame(
			['amount' => 2.5, 'currency' => 'USD', 'stashed' => 'kept'],
			$content['price']['value']['zxx'],
		);
	}

	public function testRepeaterNormalizesIndexGaps(): void
	{
		$patch = new FormPatch([
			[
				'name' => 'tags',
				'type' => 'Tags',
				'control' => [
					'name' => 'repeater',
					'props' => ['item' => ['name' => 'text', 'props' => []]],
				],
			],
		]);

		$content = $patch->content(
			['tags' => ['type' => 'Tags', 'value' => ['zxx' => ['a', 'b', 'c']]]],
			['tags' => ['value' => ['zxx' => [0 => 'a', 2 => 'c']]]],
		);

		$this->assertSame(['a', 'c'], $content['tags']['value']['zxx']);
	}

	public function testJsonSubmissionReplacesValueAndMeta(): void
	{
		$patch = new FormPatch([
			['name' => 'body', 'type' => 'RichText', 'control' => ['name' => 'element', 'props' => []]],
		]);

		$content = $patch->content(
			['body' => ['type' => 'RichText', 'value' => ['zxx' => '<p>Old</p>']]],
			['body' => ['json' => '{"value":{"zxx":"<p>New</p>"},"meta":{"tone":"loud"}}']],
		);

		$this->assertSame('<p>New</p>', $content['body']['value']['zxx']);
		$this->assertSame(['tone' => 'loud'], $content['body']['meta']);
	}

	public function testMalformedJsonSubmissionIsIgnored(): void
	{
		$patch = new FormPatch([
			['name' => 'body', 'type' => 'RichText', 'control' => ['name' => 'element', 'props' => []]],
		]);

		$content = $patch->content(
			['body' => ['type' => 'RichText', 'value' => ['zxx' => '<p>Old</p>']]],
			['body' => ['json' => '{broken']],
		);

		$this->assertSame('<p>Old</p>', $content['body']['value']['zxx']);
	}
}

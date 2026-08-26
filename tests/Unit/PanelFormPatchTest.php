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

	public function testMetaControlEntriesPatchTheMetaMap(): void
	{
		$metaControl = [
			'name' => 'group',
			'props' => [
				'fields' => [
					['key' => 'cssClass', 'control' => ['name' => 'text', 'props' => []]],
					['key' => 'columns', 'control' => ['name' => 'number', 'props' => []]],
				],
			],
		];
		$patch = new FormPatch([
			[
				'name' => 'styled',
				'type' => 'Text',
				'control' => ['name' => 'text', 'props' => []],
				'metaControl' => $metaControl,
			],
		]);

		$content = $patch->content(
			[
				'styled' => [
					'type' => 'Text',
					'value' => ['zxx' => 'Body'],
					'meta' => [
						'cssClass' => ['zxx' => 'old'],
						'stashed' => ['zxx' => 'kept'],
					],
				],
			],
			[
				'styled' => [
					'meta' => [
						'cssClass' => ['zxx' => 'wide'],
						'columns' => ['zxx' => '3'],
						'crafted' => ['zxx' => 'ignored'],
					],
				],
			],
		);

		$this->assertSame('Body', $content['styled']['value']['zxx']);
		$this->assertSame('wide', $content['styled']['meta']['cssClass']['zxx']);
		$this->assertSame(3.0, $content['styled']['meta']['columns']['zxx']);
		$this->assertSame('kept', $content['styled']['meta']['stashed']['zxx']);
		$this->assertArrayNotHasKey('crafted', $content['styled']['meta']);
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

	public function testContractFixtures(): void
	{
		$fixtures = json_decode(
			(string) file_get_contents(__DIR__ . '/../../contract/form-leaf.json'),
			true,
		);
		$this->assertNotEmpty($fixtures['cases']);

		$patch = new FormPatch([
			['name' => 'body', 'type' => 'RichText', 'control' => ['name' => 'element', 'props' => []]],
		]);

		foreach ($fixtures['cases'] as $case) {
			$leaf = $case['leafRaw'] ?? json_encode($case['leaf']);
			$content = $patch->content(['body' => $case['stored']], ['body' => ['json' => $leaf]]);

			$this->assertEquals($case['patched'], $content['body'], "contract case: {$case['name']}");
		}
	}

	private function entriesPatch(): FormPatch
	{
		return new FormPatch([
			[
				'name' => 'sections',
				'type' => 'Entries',
				'control' => [
					'name' => 'entries',
					'props' => [
						'entryTypes' => [
							[
								'type' => 'App\Node\Quote',
								'fields' => [
									[
										'name' => 'text',
										'type' => 'Text',
										'control' => ['name' => 'text', 'props' => []],
									],
									[
										'name' => 'famous',
										'type' => 'Checkbox',
										'control' => ['name' => 'checkbox', 'props' => []],
									],
								],
							],
							[
								'type' => 'App\Node\Person',
								'fields' => [
									[
										'name' => 'bio',
										'type' => 'RichText',
										'control' => ['name' => 'element', 'props' => []],
									],
								],
							],
						],
					],
				],
			],
		]);
	}

	private function entriesContent(array $rows): array
	{
		return ['sections' => ['type' => 'Entries', 'value' => ['zxx' => $rows]]];
	}

	public function testEntriesPatchRowsByUidAndKeepUnknownKeys(): void
	{
		$stored = $this->entriesContent([
			[
				'uid' => 'r1',
				'type' => 'App\Node\Quote',
				'note' => 'row-kept',
				'fields' => [
					'text' => ['type' => 'Text', 'value' => ['zxx' => 'Old A'], 'stashed' => 'kept'],
					'ghost' => ['type' => 'Unknown', 'value' => ['zxx' => 'g']],
				],
			],
			[
				'uid' => 'r2',
				'type' => 'App\Node\Quote',
				'fields' => ['text' => ['type' => 'Text', 'value' => ['zxx' => 'Old B']]],
			],
			[
				'uid' => 'r3',
				'type' => 'App\Node\Quote',
				'fields' => ['text' => ['type' => 'Text', 'value' => ['zxx' => 'Removed']]],
			],
		]);
		$submitted = $this->entriesContent([
			[
				'uid' => 'r2',
				'type' => 'App\Node\Quote',
				'fields' => [
					'text' => ['value' => ['zxx' => 'New B']],
					'famous' => ['value' => ['zxx' => '1']],
				],
			],
			[
				'uid' => 'r1',
				'type' => 'App\Node\Quote',
				'fields' => ['text' => ['value' => ['zxx' => 'New A']]],
			],
		]);

		$rows = $this->entriesPatch()->content($stored, $submitted)['sections']['value']['zxx'];

		$this->assertCount(2, $rows);
		$this->assertSame('r2', $rows[0]['uid']);
		$this->assertSame('New B', $rows[0]['fields']['text']['value']['zxx']);
		$this->assertTrue($rows[0]['fields']['famous']['value']['zxx']);
		$this->assertSame('r1', $rows[1]['uid']);
		$this->assertSame('New A', $rows[1]['fields']['text']['value']['zxx']);
		$this->assertSame('kept', $rows[1]['fields']['text']['stashed']);
		$this->assertSame('g', $rows[1]['fields']['ghost']['value']['zxx']);
		$this->assertSame('row-kept', $rows[1]['note']);
	}

	public function testEntriesBackfillMissingUid(): void
	{
		$submitted = $this->entriesContent([
			[
				'uid' => '',
				'type' => 'App\Node\Quote',
				'fields' => ['text' => ['value' => ['zxx' => 'Fresh']]],
			],
		]);

		$rows = $this->entriesPatch()->content([], $submitted)['sections']['value']['zxx'];

		$this->assertMatchesRegularExpression('/^[123456789bcdfghklmnpqrstvwxyz]{13}$/', $rows[0]['uid']);
		$this->assertSame('Fresh', $rows[0]['fields']['text']['value']['zxx']);
	}

	public function testEntriesDropUnknownTypesAndNormalizeGaps(): void
	{
		$submitted = $this->entriesContent([
			0 => [
				'uid' => 'a',
				'type' => 'App\Node\Quote',
				'fields' => ['text' => ['value' => ['zxx' => 'A']]],
			],
			2 => ['uid' => 'evil', 'type' => 'Acme\Evil', 'fields' => []],
			3 => 'not-a-row',
			4 => [
				'uid' => 'b',
				'type' => 'App\Node\Quote',
				'fields' => ['text' => ['value' => ['zxx' => 'B']]],
			],
		]);

		$rows = $this->entriesPatch()->content([], $submitted)['sections']['value']['zxx'];

		$this->assertSame(['a', 'b'], array_column($rows, 'uid'));
		$this->assertSame([0, 1], array_keys($rows));
	}

	public function testEntriesElementLeafInsideRow(): void
	{
		$submitted = $this->entriesContent([
			[
				'uid' => 'p1',
				'type' => 'App\Node\Person',
				'fields' => ['bio' => ['json' => '{"value":{"zxx":"<p>Bio</p>"},"meta":{"tone":"soft"}}']],
			],
		]);

		$rows = $this->entriesPatch()->content([], $submitted)['sections']['value']['zxx'];

		$this->assertSame('RichText', $rows[0]['fields']['bio']['type']);
		$this->assertSame('<p>Bio</p>', $rows[0]['fields']['bio']['value']['zxx']);
		$this->assertSame(['tone' => 'soft'], $rows[0]['fields']['bio']['meta']);
	}

	public function testEntriesTypeChangeIgnoresStaleStoredRow(): void
	{
		$stored = $this->entriesContent([
			[
				'uid' => 'r1',
				'type' => 'App\Node\Quote',
				'note' => 'stale',
				'fields' => ['text' => ['type' => 'Text', 'value' => ['zxx' => 'Old']]],
			],
		]);
		$submitted = $this->entriesContent([
			[
				'uid' => 'r1',
				'type' => 'App\Node\Person',
				'fields' => ['bio' => ['json' => '{"value":{"zxx":"<p>Bio</p>"}}']],
			],
		]);

		$rows = $this->entriesPatch()->content($stored, $submitted)['sections']['value']['zxx'];

		$this->assertSame(
			['uid', 'type', 'fields'],
			array_keys($rows[0]),
		);
		$this->assertSame('App\Node\Person', $rows[0]['type']);
		$this->assertSame(['bio'], array_keys($rows[0]['fields']));
	}
}

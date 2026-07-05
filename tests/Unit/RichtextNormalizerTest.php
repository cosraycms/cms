<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Richtext\Normalizer;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichtextNormalizerTest extends TestCase
{
	public function testDefaultsAreOmittedAndKeysOrdered(): void
	{
		$normalizer = new Normalizer();
		$result = $normalizer->normalize([
			'content' => [
				[
					'content' => [['text' => 'Hallo', 'type' => 'text']],
					'attrs' => ['align' => null, 'class' => 'default'],
					'type' => 'paragraph',
				],
				[
					'attrs' => ['start' => 1],
					'type' => 'orderedList',
					'content' => [
						[
							'type' => 'listItem',
							'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Eins']]]],
						],
					],
				],
				['type' => 'horizontalRule', 'attrs' => ['class' => null]],
				['type' => 'image', 'attrs' => ['uid' => 'a1', 'meta' => null]],
			],
			'type' => 'doc',
		]);

		$this->assertSame(
			[
				'type' => 'doc',
				'content' => [
					['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hallo']]],
					[
						'type' => 'orderedList',
						'content' => [
							[
								'type' => 'listItem',
								'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Eins']]]],
							],
						],
					],
					['type' => 'horizontalRule'],
					['type' => 'image', 'attrs' => ['uid' => 'a1']],
				],
			],
			$result,
		);
	}

	public function testMarksAreSortedAndLinkDefaultsDropped(): void
	{
		$normalizer = new Normalizer();
		$result = $normalizer->normalize([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [
						[
							'type' => 'text',
							'text' => 'x',
							'marks' => [
								[
									'type' => 'link',
									'attrs' => ['target' => null, 'href' => 'https://x.de', 'class' => null],
								],
								['type' => 'bold', 'attrs' => []],
							],
						],
					],
				],
			],
		]);

		$this->assertSame(
			[
				['type' => 'bold'],
				['type' => 'link', 'attrs' => ['href' => 'https://x.de']],
			],
			$result['content'][0]['content'][0]['marks'],
		);
	}

	public function testAdjacentRunsWithIdenticalMarksAreMerged(): void
	{
		$normalizer = new Normalizer();
		$result = $normalizer->normalize([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [
						['type' => 'text', 'text' => 'Hel'],
						['type' => 'text', 'text' => ''],
						['type' => 'text', 'text' => 'lo '],
						['type' => 'text', 'text' => 'fett', 'marks' => [['type' => 'bold']]],
						['type' => 'text', 'text' => 'er', 'marks' => [['type' => 'bold']]],
						['type' => 'hardBreak'],
						['type' => 'text', 'text' => 'Ende'],
					],
				],
			],
		]);

		$this->assertSame(
			[
				['type' => 'text', 'text' => 'Hello '],
				['type' => 'text', 'text' => 'fetter', 'marks' => [['type' => 'bold']]],
				['type' => 'hardBreak'],
				['type' => 'text', 'text' => 'Ende'],
			],
			$result['content'][0]['content'],
		);
	}

	public function testEmptyDocumentsBecomeNull(): void
	{
		$normalizer = new Normalizer();

		$this->assertNull($normalizer->normalize(null));
		$this->assertNull($normalizer->normalize(['type' => 'doc', 'content' => []]));
		$this->assertNull($normalizer->normalize([
			'type' => 'doc',
			'content' => [
				['type' => 'paragraph', 'content' => []],
				['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '']]],
			],
		]));
		$this->assertNotNull($normalizer->normalize([
			'type' => 'doc',
			'content' => [['type' => 'horizontalRule']],
		]));
	}

	public function testNormalizationIsIdempotent(): void
	{
		$normalizer = new Normalizer();
		$doc = [
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'attrs' => ['class' => 'intro', 'align' => 'center'],
					'content' => [
						['type' => 'text', 'text' => 'a', 'marks' => [['type' => 'italic'], ['type' => 'bold']]],
						['type' => 'text', 'text' => 'b', 'marks' => [['type' => 'bold'], ['type' => 'italic']]],
					],
				],
			],
		];

		$once = $normalizer->normalize($doc);
		$twice = $normalizer->normalize($once);

		$this->assertSame($once, $twice);
		$this->assertSame('ab', $once['content'][0]['content'][0]['text']);
	}
}

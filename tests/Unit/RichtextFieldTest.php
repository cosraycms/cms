<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\RichText;
use Cosray\Field\Services;
use Cosray\Migration\NodeContentNormalizer;
use Cosray\Richtext\Envelope;
use Cosray\Richtext\Normalizer;
use Cosray\Richtext\Scanner;
use Cosray\Tests\RichtextOwnerTestCase;
use Cosray\Uid;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichtextFieldTest extends RichtextOwnerTestCase
{
	private function field(array $settings = []): RichText
	{
		$field = new RichText('body', $this->owner($settings), new ValueContext('body', []));
		$field->init(Services::withDefaults());
		$field->translate();

		return $field;
	}

	private function envelope(?array $en = null, ?array $de = null): array
	{
		return [
			'type' => RichText::class,
			'format' => Envelope::FORMAT,
			'version' => Envelope::VERSION,
			'value' => ['en' => $en, 'de' => $de],
		];
	}

	private static function doc(string $text): array
	{
		return [
			'type' => 'doc',
			'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]],
		];
	}

	public function testShapeAcceptsTheStructuredEnvelope(): void
	{
		$result = $this
			->field()
			->shape()
			->validate($this->envelope(self::doc('Hallo')));

		$this->assertTrue($result->valid());
	}

	public function testShapeRejectsLegacyHtmlAndMissingEnvelope(): void
	{
		$field = $this->field();

		$this->assertFalse(
			$field
				->shape()
				->validate([
					'type' => RichText::class,
					'value' => ['en' => '<p>legacy</p>', 'de' => null],
				])
				->valid(),
		);

		$this->assertFalse(
			$field
				->shape()
				->validate([
					'type' => RichText::class,
					'format' => 'html',
					'version' => 1,
					'value' => ['en' => '<p>legacy</p>', 'de' => null],
				])
				->valid(),
		);
	}

	public function testShapeChecksDocsAgainstDeclaredClasses(): void
	{
		$doc = [
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'attrs' => ['class' => 'intro'],
					'content' => [['type' => 'text', 'text' => 'x']],
				],
			],
		];

		$this->assertFalse($this->field()->shape()->validate($this->envelope($doc))->valid());
		$this->assertTrue(
			$this
				->field(['richtext.classes' => ['intro' => 'Intro']])
				->shape()
				->validate($this->envelope($doc))
				->valid(),
		);
	}

	public function testContentCanonicalizationCoversFieldsAndBlocks(): void
	{
		$content = [
			'body' => [
				'type' => RichText::class,
				'format' => Envelope::FORMAT,
				'version' => 1,
				'value' => [
					'en' => [
						'type' => 'doc',
						'content' => [
							[
								'type' => 'paragraph',
								'attrs' => ['class' => 'default'],
								'content' => [
									['type' => 'text', 'text' => 'Hel'],
									['type' => 'text', 'text' => 'lo'],
								],
							],
						],
					],
					'de' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => []]]],
				],
			],
			'grid' => [
				'type' => 'Cosray\Field\Blocks',
				'value' => [
					'zxx' => [
						[
							'type' => 'richtext',
							'format' => Envelope::FORMAT,
							'version' => 1,
							'value' => ['en' => self::doc('Block'), 'de' => null],
						],
					],
				],
			],
		];

		$result = new Normalizer()->content($content);

		$this->assertSame(
			[['type' => 'text', 'text' => 'Hello']],
			$result['body']['value']['en']['content'][0]['content'],
		);
		$this->assertNull($result['body']['value']['de']);
		$this->assertSame(
			self::doc('Block'),
			$result['grid']['value']['zxx'][0]['value']['en'],
		);
	}

	public function testScanContentCollectsFieldAndBlockReferences(): void
	{
		$doc = [
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [
						['type' => 'image', 'attrs' => ['uid' => 'img1']],
						[
							'type' => 'text',
							'text' => 'x',
							'marks' => [['type' => 'link', 'attrs' => ['node' => 'n1']]],
						],
					],
				],
			],
		];
		$content = [
			'body' => [
				'type' => RichText::class,
				'format' => Envelope::FORMAT,
				'version' => 1,
				'value' => ['en' => $doc],
			],
			'grid' => [
				'type' => 'Cosray\Field\Blocks',
				'value' => [
					'zxx' => [
						[
							'type' => 'richtext',
							'format' => Envelope::FORMAT,
							'version' => 1,
							'value' => [
								'en' => [
									'type' => 'doc',
									'content' => [
										[
											'type' => 'paragraph',
											'content' => [
												[
													'type' => 'text',
													'text' => 'y',
													'marks' => [['type' => 'link', 'attrs' => ['asset' => 'doc1']]],
												],
											],
										],
									],
								],
							],
						],
					],
				],
			],
		];

		$this->assertSame(
			['assets' => ['img1', 'doc1'], 'nodes' => ['n1']],
			Scanner::scanContent($content),
		);
	}

	public function testNodeContentNormalizerPassesTheEnvelopeThrough(): void
	{
		$normalizer = new NodeContentNormalizer(new Uid('ab', 4));
		$result = $normalizer->normalize([
			'body' => [
				'type' => 'richtext',
				'format' => Envelope::FORMAT,
				'version' => 1,
				'value' => ['en' => self::doc('Hallo'), 'de' => null],
			],
			'grid' => [
				'type' => 'blocks',
				'value' => [
					'zxx' => [
						[
							'type' => 'richtext',
							'colspan' => 12,
							'rowspan' => 1,
							'format' => Envelope::FORMAT,
							'version' => 1,
							'value' => ['en' => self::doc('Block')],
						],
					],
				],
			],
		]);

		$this->assertSame(
			[
				'type' => RichText::class,
				'format' => Envelope::FORMAT,
				'version' => 1,
				'value' => ['en' => self::doc('Hallo'), 'de' => null],
			],
			$result['body'],
		);
		$this->assertSame(
			[
				'type' => 'richtext',
				'colspan' => 12,
				'rowspan' => 1,
				'format' => Envelope::FORMAT,
				'version' => 1,
				'value' => ['en' => self::doc('Block')],
			],
			$result['grid']['value']['zxx'][0],
		);
	}

	public function testLegacyContentStaysByteIdentical(): void
	{
		$normalizer = new NodeContentNormalizer(new Uid('ab', 4));
		$legacy = [
			'body' => [
				'type' => 'richtext',
				'value' => ['en' => '<p>legacy</p>', 'de' => null],
			],
		];

		$this->assertSame(
			[
				'body' => [
					'type' => RichText::class,
					'value' => ['en' => '<p>legacy</p>', 'de' => null],
				],
			],
			$normalizer->normalize($legacy),
		);
	}
}

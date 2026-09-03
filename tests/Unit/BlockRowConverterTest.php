<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Block as Builtin;
use Cosray\Field;
use Cosray\Migration\BlockRowConverter;
use Cosray\Tests\Fixtures\Field\TestBlocks;
use Cosray\Tests\TestCase;
use Cosray\Uid;

/**
 * The reshaping behind migration 000000-000031, driven over the block
 * shapes the content survey found across the sites.
 *
 * @internal
 *
 * @coversNothing
 */
final class BlockRowConverterTest extends TestCase
{
	private const string UID_PATTERN = '/^[123456789bcdfghklmnpqrstvwxyz]{13}$/';

	private const array DOC = [
		'type' => 'doc',
		'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]],
	];

	public function testConvertsEverySurveyShape(): void
	{
		$converter = $this->converter();
		$content = $converter->convert($this->legacyContent(), 'nodes', '7');
		$field = $content['content'];
		$de = $field['value']['de'];

		$this->assertSame($this->legacyContent()['title'], $content['title']);
		$this->assertSame(TestBlocks::class, $field['type']);
		$this->assertSame(['note' => ['zxx' => 'kept']], $field['meta']);
		$this->assertNull($field['value']['it']);

		// Spans, an indent from colstart, the envelope moving into the field, the class meta.
		$this->assertSame(
			[
				'uid' => 'rich000000001',
				'type' => Builtin\RichText::class,
				'layout' => ['span' => 8, 'rows' => 1, 'indent' => 2],
				'fields' => [
					'text' => [
						'type' => Field\RichText::class,
						'format' => 'cosray-richtext',
						'version' => 1,
						'value' => ['zxx' => self::DOC],
					],
				],
				'meta' => ['class' => ['zxx' => 'lead']],
			],
			$de[0],
		);

		// The legacy `html` id.
		$this->assertSame(Builtin\RichText::class, $de[1]['type']);
		$this->assertSame(['span' => 12, 'rows' => 1, 'indent' => 0], $de[1]['layout']);
		$this->assertSame('cosray-richtext', $de[1]['fields']['text']['format']);
		$this->assertArrayNotHasKey('meta', $de[1]);

		// A row span and the dropped width.
		$this->assertSame(
			[
				'uid' => 'text000000001',
				'type' => Builtin\Text::class,
				'layout' => ['span' => 6, 'rows' => 2, 'indent' => 0],
				'fields' => [
					'text' => ['type' => Field\Textarea::class, 'value' => ['zxx' => "Mo-Fr\n9-17"]],
				],
			],
			$de[2],
		);

		// Headings become the one heading type with a string level.
		$this->assertSame(Builtin\Heading::class, $de[3]['type']);
		$this->assertSame(
			[
				'text' => ['type' => Field\Text::class, 'value' => ['zxx' => 'Öffnungszeiten']],
				'level' => ['type' => Field\Option::class, 'value' => ['zxx' => '2']],
			],
			$de[3]['fields'],
		);

		// Media lists move under the neutral locale of the media field.
		$this->assertSame(Builtin\Image::class, $de[4]['type']);
		$this->assertSame(['span' => 4, 'rows' => 2, 'indent' => 0], $de[4]['layout']);
		$this->assertSame(
			[
				'image' => [
					'type' => Field\Image::class,
					'value' => ['zxx' => [['uid' => 'asset00000001', 'meta' => ['alt' => ['zxx' => 'Bild']]]]],
				],
			],
			$de[4]['fields'],
		);
		$this->assertSame(Builtin\Images::class, $de[5]['type']);
		$this->assertSame(
			['asset00000002', 'asset00000003'],
			array_column($de[5]['fields']['images']['value']['zxx'], 'uid'),
		);
		// The single-video field holds one item; the surplus is dropped and reported.
		$this->assertSame(Builtin\Video::class, $de[6]['type']);
		$this->assertSame(
			['video' => ['type' => Field\Video::class, 'value' => ['zxx' => [['uid' => 'asset00000004']]]]],
			$de[6]['fields'],
		);

		// The aspect ratio leaves the block meta for the field meta; class and id stay.
		$this->assertSame(
			[
				'uid' => 'yout000000001',
				'type' => Builtin\Youtube::class,
				'layout' => ['span' => 6, 'rows' => 1, 'indent' => 6],
				'fields' => [
					'video' => [
						'type' => Field\Youtube::class,
						'value' => ['zxx' => 'dQw4w9WgXcQ'],
						'meta' => ['aspectRatioX' => ['zxx' => 4], 'aspectRatioY' => ['zxx' => 3]],
					],
				],
				'meta' => ['class' => ['zxx' => 'wide'], 'id' => ['zxx' => 'promo']],
			],
			$de[7],
		);

		// A missing uid is generated, empty meta pruned, other meta kept.
		$this->assertSame(Builtin\Iframe::class, $de[8]['type']);
		$this->assertMatchesRegularExpression(self::UID_PATTERN, $de[8]['uid']);
		$this->assertSame(
			[
				'code' => [
					'type' => Field\Iframe::class,
					'value' => ['zxx' => '<iframe src="https://example.com"></iframe>'],
				],
			],
			$de[8]['fields'],
		);
		$this->assertSame(['custom' => ['zxx' => 'kept']], $de[8]['meta']);

		// Unknown types are left as they are.
		$this->assertSame($this->legacyContent()['content']['value']['de'][9], $de[9]);

		// Legacy HTML without the envelope keeps its markless value.
		$this->assertSame(
			['text' => ['type' => Field\RichText::class, 'value' => ['zxx' => '<p>old</p>']]],
			$de[10]['fields'],
		);

		// Numeric strings and a value keyed by the list's own locale.
		$en = $field['value']['en'];
		$this->assertCount(4, $en);
		$this->assertSame(['span' => 12, 'rows' => 1, 'indent' => 0], $en[0]['layout']);
		$this->assertSame(['zxx' => 'Opening hours'], $en[0]['fields']['text']['value']);
		// A float span, a row span below one, a scalar value and unusable meta.
		$this->assertSame(['span' => 6, 'rows' => 1, 'indent' => 0], $en[1]['layout']);
		$this->assertSame(['zxx' => 'plain'], $en[1]['fields']['text']['value']);
		$this->assertArrayNotHasKey('meta', $en[1]);
		// A map without the neutral or the list's locale yields its first entry.
		$this->assertSame(['zxx' => 'Bonjour'], $en[2]['fields']['text']['value']);
		$this->assertSame(['zxx' => '5'], $en[2]['fields']['level']['value']);
		$this->assertSame(['zxx' => null], $en[3]['fields']['code']['value']);

		// Blocks fields are found at any depth.
		$nested = $content['items']['value']['zxx'][0]['fields']['body']['value']['zxx'][0];
		$this->assertSame(Builtin\Heading::class, $nested['type']);
		$this->assertSame(['zxx' => '4'], $nested['fields']['level']['value']);

		$this->assertSame(
			[
				'fields' => 2,
				'blocks' => 15,
				'types' => [
					'richtext' => 2,
					'html' => 1,
					'text' => 3,
					'h2' => 1,
					'image' => 1,
					'images' => 1,
					'video' => 1,
					'youtube' => 1,
					'iframe' => 2,
					'h5' => 1,
					'h4' => 1,
				],
				'uidsGenerated' => 1,
				'legacyRichtext' => 1,
				'droppedMediaItems' => 1,
				'droppedItems' => 1,
				'metaKeys' => ['class' => 2, 'id' => 1, 'custom' => 1],
				'unknownTypes' => [
					[
						'table' => 'nodes',
						'row' => '7',
						'field' => 'content',
						'locale' => 'de',
						'index' => 9,
						'type' => 'map',
					],
				],
				'unresolvedFieldTypes' => [],
			],
			$converter->report(),
		);
	}

	public function testLeavesConvertedRowsAndForeignShapesAlone(): void
	{
		$once = $this->converter()->convert($this->legacyContent());
		$again = $this->converter();

		$this->assertSame($once, $again->convert($once));
		$this->assertSame(2, $again->report()['fields']);
		$this->assertSame(0, $again->report()['blocks']);
		$this->assertSame('map', $again->report()['unknownTypes'][0]['type']);

		$foreign = [
			// Before migration 017: a bare id and a plain list.
			'legacy' => ['type' => 'blocks', 'value' => [['type' => 'text', 'colspan' => 12, 'value' => 'old']]],
			// Unknown field classes whose values do not look like blocks.
			'gone' => ['type' => 'App\Gone', 'value' => ['zxx' => [['foo' => 'bar']]]],
			'scalar' => ['type' => 'App\Gone', 'value' => ['zxx' => 'text']],
			'listed' => ['type' => 'App\Gone', 'value' => [['colspan' => 12]]],
			'keyed' => ['type' => 'App\Gone', 'value' => ['Foo' => [['colspan' => 12]]]],
			'text' => ['type' => Field\Text::class, 'value' => ['zxx' => 'x'], 'meta' => ['columns' => ['zxx' => 3]]],
		];
		$converter = $this->converter();

		$this->assertSame($foreign, $converter->convert($foreign));
		$this->assertSame(0, $converter->report()['fields']);
	}

	public function testFallsBackToTheValueShapeWhenTheFieldClassIsGone(): void
	{
		$converter = $this->converter();
		$converted = $converter->convert(
			[
				'content' => [
					'type' => 'App\Gone',
					'value' => [
						'de' => [[
							'type' => 'text',
							'uid' => 'text000000009',
							'colspan' => 3,
							'rowspan' => 1,
							'value' => ['zxx' => 'x'],
						]],
						'en' => null,
					],
					'meta' => ['columns' => ['zxx' => 6]],
				],
			],
			'drafts',
			'3',
		);
		$field = $converted['content'];

		$this->assertSame('App\Gone', $field['type']);
		$this->assertArrayNotHasKey('meta', $field);
		$this->assertSame(Builtin\Text::class, $field['value']['de'][0]['type']);
		$this->assertSame(['span' => 3, 'rows' => 1, 'indent' => 0], $field['value']['de'][0]['layout']);
		$this->assertNull($field['value']['en']);
		$this->assertSame(['App\Gone' => 1], $converter->report()['unresolvedFieldTypes']);
	}

	private function converter(): BlockRowConverter
	{
		return new BlockRowConverter(new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13));
	}

	/** Node content as migrations 017–020 leave it, one block per survey shape. */
	private function legacyContent(): array
	{
		$envelope = ['format' => 'cosray-richtext', 'version' => 1];

		return [
			'title' => ['type' => Field\Text::class, 'value' => ['de' => 'Seite', 'en' => 'Page']],
			'content' => [
				'type' => TestBlocks::class,
				'value' => [
					'de' => [
						[
							'type' => 'richtext',
							'uid' => 'rich000000001',
							'colspan' => 8,
							'rowspan' => 1,
							'colstart' => 3,
							...$envelope,
							'value' => ['zxx' => self::DOC],
							'meta' => ['class' => ['zxx' => 'lead']],
						],
						[
							'type' => 'html',
							'uid' => 'html000000001',
							'colspan' => 12,
							'rowspan' => 1,
							'colstart' => null,
							...$envelope,
							'value' => ['zxx' => self::DOC],
						],
						[
							'type' => 'text',
							'uid' => 'text000000001',
							'colspan' => 6,
							'rowspan' => 2,
							'width' => 50,
							'value' => ['zxx' => "Mo-Fr\n9-17"],
						],
						[
							'type' => 'h2',
							'uid' => 'head000000001',
							'colspan' => 12,
							'rowspan' => 1,
							'colstart' => null,
							'value' => ['zxx' => 'Öffnungszeiten'],
						],
						[
							'type' => 'image',
							'uid' => 'imag000000001',
							'colspan' => 4,
							'rowspan' => 2,
							'colstart' => null,
							'value' => [['uid' => 'asset00000001', 'meta' => ['alt' => ['zxx' => 'Bild']]]],
						],
						[
							'type' => 'images',
							'uid' => 'imgs000000001',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => [['uid' => 'asset00000002'], ['uid' => 'asset00000003']],
						],
						[
							'type' => 'video',
							'uid' => 'vide000000001',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => [['uid' => 'asset00000004'], ['uid' => 'asset00000005']],
						],
						[
							'type' => 'youtube',
							'uid' => 'yout000000001',
							'colspan' => 6,
							'rowspan' => 1,
							'colstart' => 7,
							'value' => ['zxx' => 'dQw4w9WgXcQ'],
							'meta' => [
								'aspectRatioX' => ['zxx' => 4],
								'aspectRatioY' => ['zxx' => 3],
								'class' => ['zxx' => 'wide'],
								'id' => ['zxx' => 'promo'],
							],
						],
						[
							'type' => 'iframe',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => ['zxx' => '<iframe src="https://example.com"></iframe>'],
							'meta' => ['class' => ['zxx' => ''], 'custom' => ['zxx' => 'kept']],
						],
						[
							'type' => 'map',
							'uid' => 'map0000000001',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => ['zxx' => 'geo'],
						],
						[
							'type' => 'richtext',
							'uid' => 'rich000000002',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => ['zxx' => '<p>old</p>'],
						],
					],
					'en' => [
						[
							'type' => 'text',
							'uid' => 'text000000002',
							'colspan' => '12',
							'rowspan' => '1',
							'colstart' => '1',
							'value' => ['en' => 'Opening hours'],
						],
						[
							'type' => 'text',
							'uid' => 'text000000003',
							'colspan' => 6.0,
							'rowspan' => 0,
							'value' => 'plain',
							'meta' => 'nope',
						],
						[
							'type' => 'h5',
							'uid' => 'head000000003',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => ['fr' => 'Bonjour'],
						],
						[
							'type' => 'iframe',
							'uid' => 'ifrm000000001',
							'colspan' => 12,
							'rowspan' => 1,
							'value' => [],
						],
						'junk',
					],
					'it' => null,
				],
				'meta' => ['columns' => ['zxx' => 12], 'minCellWidth' => ['zxx' => 2], 'note' => ['zxx' => 'kept']],
			],
			'items' => [
				'type' => Field\Entries::class,
				'value' => [
					'zxx' => [
						[
							'uid' => 'entry00000001',
							'type' => 'App\Entry',
							'fields' => [
								'body' => [
									'type' => Field\Blocks::class,
									'value' => [
										'zxx' => [
											[
												'type' => 'h4',
												'uid' => 'head000000002',
												'colspan' => 12,
												'rowspan' => 1,
												'value' => ['zxx' => 'Nested'],
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
	}
}

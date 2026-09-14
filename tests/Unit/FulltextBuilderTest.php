<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Block\Registry;
use Cosray\Contract\Embedded;
use Cosray\Contract\Title;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Checkbox;
use Cosray\Field\Code;
use Cosray\Field\Entries;
use Cosray\Field\Iframe;
use Cosray\Field\RichText;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Fulltext\Builder;
use Cosray\Locales;
use Cosray\Node\Types;
use Cosray\Schema\Allows;
use Cosray\Schema\Fulltext;
use Cosray\Schema\FulltextWeight as Weight;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;
use Cosray\Schema\When;
use PHPUnit\Framework\TestCase;

final class FulltextBuilderTest extends TestCase
{
	private function build(
		string $class,
		array $content,
		array $titles = [],
		string $locale = 'en',
	): \Cosray\Fulltext\Document {
		$locales = new Locales();
		$locales->add('en', 'English');
		$locales->add('de', 'German', fallback: 'en');
		return new Builder(new Types(), Registry::withDefaults())->build(
			$class,
			$content,
			$titles,
			$locales->get($locale),
		);
	}

	private function text(string $text): array
	{
		return ['value' => ['zxx' => $text]];
	}

	public function testOnlySelectedTextEntersTheDocumentAndSource(): void
	{
		$doc = $this->build(FtsSelection::class, [
			'title' => $this->text('Implicit title stays private'),
			'body' => $this->text('Selected prose'),
			'private' => $this->text('Confidential'),
			'unknown' => $this->text('Unmodeled'),
		]);
		self::assertSame([['field' => 'body', 'weight' => 'B', 'text' => 'Selected prose']], $doc->contributions);
		self::assertSame('Selected prose', $doc->source);
	}

	public function testRowsInheritWeightsOverrideExcludeAndIgnoreMetadata(): void
	{
		$row = [
			'uid' => 'private-uid',
			'type' => FtsEntry::class,
			'meta' => ['secret' => 'private-meta'],
			'fields' => [
				'name' => $this->text('Inherited'),
				'summary' => $this->text('Important'),
				'private' => $this->text('Secret'),
				'code' => $this->text('Internal code'),
				'iframe' => $this->text('<iframe src="secret"></iframe>'),
			],
		];
		$doc = $this->build(FtsRows::class, ['entries' => ['value' => ['zxx' => [$row]]]]);
		self::assertSame(['D', 'A'], array_column($doc->contributions, 'weight'));
		self::assertSame("Inherited\n\nImportant", $doc->source);
	}

	public function testUnselectedContainersStillDiscoverSelectedChildrenButExclusionStopsTheSubtree(): void
	{
		$rows = [
			'value' => [
				'zxx' => [[
					'type' => FtsEntry::class,
					'fields' => [
						'name' => $this->text('Not opted in'),
						'summary' => $this->text('Chosen child'),
					],
				]],
			],
		];
		self::assertSame('Chosen child', $this->build(FtsRows::class, ['unselected' => $rows])->source);
		self::assertSame('', $this->build(FtsRows::class, ['excluded' => $rows])->source);
	}

	public function testUnknownAndDisallowedRowsCannotLeakText(): void
	{
		$rows = [
			'value' => [
				'zxx' => [
					['type' => FtsSelection::class, 'fields' => ['body' => $this->text('Disallowed')]],
					['type' => 'NoSuchClass', 'fields' => ['summary' => $this->text('Unknown')]],
				],
			],
		];
		self::assertSame('', $this->build(FtsRows::class, ['entries' => $rows])->source);
	}

	public function testEffectiveLocalesSelectOneTranslationThenFallbackThenNeutral(): void
	{
		$content = ['body' => ['value' => ['de' => 'Deutsch', 'en' => 'English', 'zxx' => 'Neutral']]];
		self::assertSame('Deutsch', $this->build(FtsSelection::class, $content, locale: 'de')->source);
		$content['body']['value']['de'] = '';
		self::assertSame('English', $this->build(FtsSelection::class, $content, locale: 'de')->source);
		$content['body']['value']['en'] = null;
		self::assertSame('Neutral', $this->build(FtsSelection::class, $content, locale: 'de')->source);
	}

	public function testSymmetricAndAsymmetricBlockListsFollowDisplayedFallbacks(): void
	{
		$make = static fn(array $value): array => [
			'type' => \Cosray\Block\Heading::class,
			'fields' => ['text' => ['value' => $value]],
		];
		$doc = $this->build(
			FtsRows::class,
			[
				'blocks' => ['value' => ['zxx' => [$make(['en' => 'Shared English', 'de' => 'Shared German'])]]],
				'translated' => ['value' => ['en' => [$make(['zxx' => 'English list'])], 'de' => []]],
			],
			locale: 'de',
		);
		self::assertSame("Shared German\n\nEnglish list", $doc->source);
		$doc = $this->build(
			FtsRows::class,
			[
				'translated' => [
					'value' => [
						'en' => [$make(['zxx' => 'English list'])],
						'de' => [$make(['zxx' => 'German list'])],
					],
				],
			],
			locale: 'de',
		);
		self::assertSame('German list', $doc->source);
	}

	public function testConditionsUseTheOwningScopeAndDoNotExposeDormantText(): void
	{
		$row = [
			'type' => FtsConditionalRow::class,
			'fields' => [
				'enabled' => ['value' => ['zxx' => false]],
				'body' => $this->text('Dormant row'),
			],
		];
		$content = [
			'enabled' => ['value' => ['zxx' => true]],
			'body' => $this->text('Active node'),
			'entries' => ['value' => ['zxx' => [$row]]],
		];
		self::assertSame('Active node', $this->build(FtsConditional::class, $content)->source);
		$content['enabled']['value']['zxx'] = false;
		$content['entries']['value']['zxx'][0]['fields']['enabled']['value']['zxx'] = true;
		self::assertSame('Dormant row', $this->build(FtsConditional::class, $content)->source);
	}

	public function testRichtextPreservesMarkedRunsAndSeparatesParagraphsListsAndBreaks(): void
	{
		$text = static fn(string $text): array => ['type' => 'text', 'text' => $text];
		$doc = $this->build(FtsRich::class, [
			'body' => [
				'format' => 'cosray-richtext',
				'version' => 1,
				'value' => [
					'zxx' => [
						'type' => 'doc',
						'content' => [
							[
								'type' => 'paragraph',
								'content' => [
									$text('co'),
									[...$text('operate'), 'marks' => [['type' => 'bold']]],
									$text(' now'),
									['type' => 'hardBreak'],
									$text('next'),
								],
							],
							['type' => 'paragraph', 'content' => [$text('paragraph')]],
							[
								'type' => 'bulletList',
								'content' => [
									[
										'type' => 'listItem',
										'content' => [['type' => 'paragraph', 'content' => [$text('item one')]]],
									],
									[
										'type' => 'listItem',
										'content' => [['type' => 'paragraph', 'content' => [$text('item two')]]],
									],
								],
							],
							[
								'type' => 'paragraph',
								'content' => [
									[
										...$text('Visible link'),
										'marks' => [['type' => 'link', 'attrs' => ['href' => 'secret-target']]],
									],
									[
										'type' => 'image',
										'attrs' => ['uid' => 'secret-image', 'meta' => ['alt' => 'secret-alt']],
									],
								],
							],
						],
					],
				],
			],
		]);
		self::assertSame("cooperate now\nnext\nparagraph\nitem one\n\nitem two\n\n\nVisible link", $doc->source);
	}

	public function testUnknownRichtextNodesAreSkippedWithTheirSubtreeLikeTheRenderer(): void
	{
		$text = static fn(string $text): array => ['type' => 'text', 'text' => $text];
		$doc = $this->build(FtsRich::class, [
			'body' => [
				'format' => 'cosray-richtext',
				'version' => 1,
				'value' => [
					'zxx' => [
						'type' => 'doc',
						'content' => [
							[
								'type' => 'paragraph',
								'content' => [
									$text('before '),
									['type' => 'mention', 'attrs' => ['label' => 'hidden label']],
									$text('after'),
								],
							],
							[
								'type' => 'figure',
								'content' => [['type' => 'paragraph', 'content' => [$text('hidden caption')]]],
							],
							[
								'type' => 'paragraph',
								'content' => [['type' => 'text', 'text' => 42], 'not a node', $text('last')],
							],
						],
					],
				],
			],
		]);
		self::assertSame("before after\nlast", $doc->source);
	}

	public function testFlatEmbeddedFieldsAndOnlyTheResolvedTitleProviderContribute(): void
	{
		$content = ['body' => $this->text('Flat body')];
		$doc = $this->build(FtsEmbedded::class, $content, ['en' => 'Stored title'], 'de');
		self::assertSame("Stored title\n\nFlat body", $doc->source);
		self::assertSame(['A', 'B'], array_column($doc->contributions, 'weight'));
		self::assertFalse($doc->missingTitle);
		self::assertTrue($this->build(FtsEmbedded::class, $content)->missingTitle);
		self::assertSame('Flat body', $this->build(FtsOuter::class, $content, [
			'zxx' => 'Unselected outer title',
		])->source);
		self::assertSame(
			'Inherited title',
			$this->build(FtsInherited::class, [], ['zxx' => 'Inherited title'])->source,
		);
	}

	public function testMarkersCannotBeForgedBySourceContent(): void
	{
		self::assertSame(
			'Original text',
			$this->build(FtsSelection::class, ['body' => $this->text("\0Original \x01text\x02")])->source,
		);
	}

	public function testFieldInitializationRetainsOptInAndExplicitExclusion(): void
	{
		$owner = $this->createStub(\Cosray\Field\Owner::class);
		$services = \Cosray\Field\Services::withDefaults();
		$field = new Textarea('body', $owner, new \Cosray\Value\ValueContext('body', []));
		self::assertNull($field->fulltextWeight());
		$field->init($services, new \ReflectionProperty(FtsSelection::class, 'body'));
		self::assertSame(Weight::B, $field->fulltextWeight());
		self::assertFalse($field->fulltext(false)->fulltextWeight());
	}

	public function testFieldInitializationRejectsEmbedMarkupOptIn(): void
	{
		$field = new Iframe(
			'embed',
			$this->createStub(\Cosray\Field\Owner::class),
			new \Cosray\Value\ValueContext('embed', []),
		);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Fulltext does not support');
		$field->init(\Cosray\Field\Services::withDefaults(), new \ReflectionProperty(FtsUnsupported::class, 'embed'));
	}

	public function testMalformedSelectedValuesFailWithoutFallingBackToJson(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("field 'body' must contain text");
		$this->build(FtsSelection::class, ['body' => ['value' => ['en' => ['private-key' => 'private-value']]]]);
	}

	public function testUnsupportedFieldAnnotationsFailEvenWithoutStoredValues(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Fulltext does not support');
		$this->build(FtsUnsupported::class, []);
	}

	public function testArbitraryMethodAnnotationsFailInsteadOfCallingCode(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Title contract's title() implementation");
		$this->build(FtsMethod::class, []);
	}
}

class FtsSelection
{
	protected Text $title;
	#[Fulltext(Weight::B)]
	protected Textarea $body;
	#[Fulltext(false)]
	protected Text $private;
}
class FtsEntry
{
	protected Text $name;
	#[Fulltext(Weight::A)]
	protected Text $summary;
	#[Fulltext(false)]
	protected Text $private;
	protected Code $code;
	protected Iframe $iframe;
}
class FtsRows
{
	#[Fulltext(Weight::D), Allows(FtsEntry::class)]
	protected Entries $entries;
	#[Allows(FtsEntry::class)]
	protected Entries $unselected;
	#[Fulltext(false), Allows(FtsEntry::class)]
	protected Entries $excluded;
	#[Fulltext(Weight::D), Translate]
	protected Blocks $blocks;
	#[Fulltext(Weight::D), Translate(TranslateMode::Asymmetric)]
	protected Blocks $translated;
}
class FtsConditionalRow
{
	protected Checkbox $enabled;
	#[Fulltext(Weight::B), When('enabled')]
	protected Text $body;
}
class FtsConditional extends FtsConditionalRow
{
	#[Fulltext(Weight::D), Allows(FtsConditionalRow::class)]
	protected Entries $entries;
}
class FtsRich
{
	#[Fulltext(Weight::D)]
	protected RichText $body;
}
class FtsBase implements Embedded, Title
{
	#[Fulltext(Weight::B)]
	protected Text $body;

	#[Fulltext(Weight::A)]
	public function title(): string
	{
		throw new \LogicException('Indexing must not call title methods.');
	}
}
class FtsEmbedded
{
	protected FtsBase $base;
}
class FtsOuter extends FtsEmbedded implements Title
{
	public function title(): string
	{
		throw new \LogicException('Indexing must not call title methods.');
	}
}
class FtsInherited extends FtsBase {}
class FtsUnsupported
{
	#[Fulltext(Weight::A)]
	protected Iframe $embed;
}
class FtsMethod
{
	#[Fulltext(Weight::A)]
	public function arbitrary(): string
	{
		throw new \LogicException('Not a title.');
	}
}

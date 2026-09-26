<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Block as Builtin;
use Cosray\Block\Registry;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Field;
use Cosray\Field\Image;
use Cosray\Field\RichText;
use Cosray\Field\Schema\ColumnsHandler;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Node\FieldOwner;
use Cosray\Node\Types as NodeTypes;
use Cosray\Schema\Columns;
use Cosray\Schema\Responsive;
use Cosray\Schema\Tool;
use Cosray\Schema\TranslateMode;
use Cosray\Tests\Fixtures\Block\LabelledBlock;
use Cosray\Tests\Fixtures\Block\NestedBlocksBlock;
use Cosray\Tests\Fixtures\Block\NestedEntriesBlock;
use Cosray\Tests\Fixtures\Block\NoteBlock;
use Cosray\Tests\Fixtures\Block\QuoteBlock;
use Cosray\Tests\Fixtures\Block\TextBlock;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\TestCase;
use Cosray\Value\Blocks as BlocksValue;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlocksTest extends TestCase
{
	private function createContext(): Context
	{
		$psrRequest = $this->psrRequest();
		$locales = new \Cosray\Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', domains: ['www.example.de'], fallback: 'en');

		$psrRequest = $psrRequest
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());

		return new Context(
			$this->db(),
			new \Celema\Core\Request($psrRequest),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	private function createBlocks(array $data = [], int $columns = 12, int $min = 2): Blocks
	{
		$owner = new FieldOwner($this->createContext(), 'test-node');
		$blocks = new Blocks('content', $owner, new \Cosray\Value\ValueContext('content', $data));
		$blocks->init(self::blockServices());
		$blocks->columns($columns, $min);

		return $blocks;
	}

	private function textRow(string $uid, string $text, array $layout = [], array $meta = []): array
	{
		$row = [
			'uid' => $uid,
			'type' => TextBlock::class,
			'layout' => $layout,
			'fields' => [
				'text' => ['type' => Textarea::class, 'value' => [Field::NEUTRAL_LOCALE => $text]],
			],
		];

		if ($meta !== []) {
			$row['meta'] = $meta;
		}

		return $row;
	}

	private function split(array $blocks, array $layout, string $uid = 's1'): array
	{
		return ['uid' => $uid, 'layout' => $layout, 'blocks' => $blocks];
	}

	public function testBlocksFieldCreation(): void
	{
		$blocks = $this->createBlocks();

		$this->assertInstanceOf(BlocksValue::class, $blocks->value());
		$this->assertSame([...Registry::withDefaults()->all(), TextBlock::class], $blocks->allowedBlockTypes());
		$this->assertSame(['text', 'level'], array_keys($blocks->blockFields(Builtin\Heading::class)));
		$this->assertSame(['text'], array_keys($blocks->blockFields()));
		$this->assertTrue($blocks->allows(Builtin\Image::class));
		$this->assertFalse($blocks->allows(QuoteBlock::class));
	}

	public function testControlCarriesBlockTypesAndGrid(): void
	{
		Verba::activate(new Translator('de', ['blocks' => self::root() . '/tests/Fixtures/lang']));

		try {
			$properties = $this->createBlocks()->properties();
		} finally {
			Verba::deactivate();
		}

		$control = $properties['control'];
		$types = array_column($control['props']['blockTypes'], null, 'type');

		$this->assertSame(Blocks::class, $properties['type']);
		$this->assertSame('blocks', $control['name']);
		$this->assertSame(12, $control['props']['columns']);
		$this->assertSame(2, $control['props']['min']);
		$this->assertSame('stack', $control['props']['responsive']);
		$this->assertSame('richtext', $types[Builtin\RichText::class]['handle']);
		$this->assertSame('richtext (de)', $types[Builtin\RichText::class]['label']);
		$this->assertSame('heading', $types[Builtin\Heading::class]['handle']);
		$this->assertSame('heading (de)', $types[Builtin\Heading::class]['label']);
		$this->assertSame(['text', 'level'], array_column($types[Builtin\Heading::class]['fields'], 'name'));
		$this->assertSame([], $types[Builtin\Heading::class]['fieldsets']);
		// Rich sub-fields arrive resolved to their element form.
		$this->assertSame('element', $types[Builtin\RichText::class]['fields'][0]['control']['name']);
		$this->assertArrayNotHasKey('richtextClasses', $properties);
	}

	public function testOneColumnFieldIsTheDefault(): void
	{
		$owner = new FieldOwner($this->createContext(), 'test-node');
		$blocks = new Blocks('content', $owner, new \Cosray\Value\ValueContext('content', []));
		$blocks->init(self::blockServices());
		$control = $blocks->control()->array();

		$this->assertSame(1, $control['props']['columns']);
		$this->assertSame(1, $control['props']['min']);
		$this->assertSame(Responsive::Stack, $blocks->getResponsive());
	}

	public function testBlockFieldsWithoutAnyTypeThrows(): void
	{
		$owner = new FieldOwner($this->createContext(), 'test-node');
		$blocks = new Blocks('content', $owner, new \Cosray\Value\ValueContext('content', []));
		$blocks->init(new Services(\Cosray\Field\Schema\Registry::withDefaults(), new NodeTypes(), new Registry()));

		$this->assertSame([], $blocks->allowedBlockTypes());
		$this->throws(RuntimeException::class, "Blocks field 'content' offers no block types");

		$blocks->blockFields();
	}

	public function testColumnsRejectInvalidBounds(): void
	{
		$blocks = $this->createBlocks();

		try {
			$blocks->columns(0);
			$this->fail('Zero columns must be rejected');
		} catch (\ValueError $e) {
			$this->assertStringContainsString('$columns', $e->getMessage());
		}

		$this->throws(\ValueError::class, '$min must be >= 1 and <= 12');
		$blocks->columns(12, 13);
	}

	public function testColumnsAttributeRequiresABlocksField(): void
	{
		$text = new Text(
			'title',
			$this->createStub(\Cosray\Field\Owner::class),
			new \Cosray\Value\ValueContext('title', []),
		);
		$handler = new ColumnsHandler();

		$this->assertSame([], $handler->properties(new Columns(12), $text));
		$this->throws(RuntimeException::class, 'cannot be used with the capability');

		$handler->apply(new Columns(12), $text);
	}

	public function testAllowRestrictsTheOfferedTypes(): void
	{
		$blocks = $this->createBlocks()->allow(QuoteBlock::class, TextBlock::class);
		$types = array_column($blocks->control()->array()['props']['blockTypes'], 'handle', 'type');

		$this->assertSame([QuoteBlock::class => 'quote-block', TextBlock::class => 'text'], $types);
		$this->assertTrue($blocks->allows(QuoteBlock::class));
		$this->assertFalse($blocks->allows(Builtin\Image::class));
		$this->assertSame(['text', 'source'], array_keys($blocks->blockFields(QuoteBlock::class)));
	}

	public function testASingleFieldBlockDropsItsSubFieldLabel(): void
	{
		$blocks = $this->createBlocks()->allow(
			TextBlock::class,
			QuoteBlock::class,
			LabelledBlock::class,
		);
		$types = array_column($blocks->control()->array()['props']['blockTypes'], 'labels', 'type');

		// One field: the block's own label already names it.
		$this->assertFalse($types[TextBlock::class]);
		// Two fields say different things, so both keep their labels.
		$this->assertTrue($types[QuoteBlock::class]);
		// One field, but the type asked for the label back.
		$this->assertTrue($types[LabelledBlock::class]);
	}

	public function testBlockSubFieldsCarryTheBlockPresentation(): void
	{
		$blocks = $this->createBlocks()->allow(QuoteBlock::class);
		$fields = array_column($blocks->control()->array()['props']['blockTypes'], 'fields', 'type');

		$this->assertCount(2, $fields[QuoteBlock::class]);

		foreach ($fields[QuoteBlock::class] as $field) {
			$this->assertSame('block', $field['presentation']);
		}
	}

	public function testBlocksDoNotMarkTheirFieldsAsRequired(): void
	{
		$blocks = $this->createBlocks()->allow(TextBlock::class);
		$fields = array_column($blocks->control()->array()['props']['blockTypes'], 'fields', 'type');

		// The panel is told nothing about it …
		$this->assertArrayNotHasKey('required', $fields[TextBlock::class][0]);

		// … while the shape, built from the field itself, still insists.
		$result = $blocks
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [
					Field::NEUTRAL_LOCALE => [
						$this->textRow('b1', '', ['colspan' => 2, 'rowspan' => 1, 'col' => 1, 'row' => 1]),
					],
				],
			]);

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has(['value', Field::NEUTRAL_LOCALE, 0, 'fields', 'text', 'value', 'zxx']));
	}

	public function testImageSubFieldMetaValidatesTheGallerySettings(): void
	{
		$blocks = $this->createBlocks()->allow(Builtin\Images::class);
		$content = static fn(array $meta): array => [
			'type' => Blocks::class,
			'value' => [
				Field::NEUTRAL_LOCALE => [[
					'uid' => 'b1',
					'type' => Builtin\Images::class,
					'layout' => ['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1],
					'fields' => [
						'images' => [
							'type' => Image::class,
							'value' => [Field::NEUTRAL_LOCALE => [['uid' => 'img']]],
							'meta' => $meta,
						],
					],
				]],
			],
		];

		$this->assertTrue(
			$blocks
				->shape()
				->validate($content([
					'ratio' => ['zxx' => '4/3'],
					'crop' => ['zxx' => true],
					'other' => ['zxx' => 'kept'],
				]))
				->valid(),
		);
		$this->assertTrue($blocks->shape()->validate($content([]))->valid());

		$result = $blocks->shape()->validate($content(['ratio' => ['zxx' => '5/4']]));

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has([
			'value',
			Field::NEUTRAL_LOCALE,
			0,
			'fields',
			'images',
			'meta',
			'ratio',
			'zxx',
		]));
	}

	public function testSpacingMetaGroupsOfferTheTokens(): void
	{
		$properties = $this
			->createBlocks()
			->allow(TextBlock::class)
			->properties();
		$tokens = static fn(array $control): array => array_column($control['props']['options'], 'value');
		$gaps = $properties['metaControl']['props']['fields'];
		$blockMeta = $properties['control']['props']['meta']['props']['fields'];

		$this->assertSame(['gap', 'rowGap', 'columnGap'], array_column($gaps, 'key'));
		$this->assertSame(['', 'none', 's', 'm', 'l', 'xl'], $tokens($gaps[0]['control']));
		$this->assertSame(['class', 'id', 'padding'], array_column($blockMeta, 'key'));
		$this->assertSame(['', 'none', 's', 'm', 'l', 'xl'], $tokens($blockMeta[2]['control']));
	}

	public function testSpacingMetaValidatesTheTokens(): void
	{
		$blocks = $this->createBlocks()->allow(TextBlock::class);
		$content = fn(array $meta, array $rowMeta): array => [
			'type' => Blocks::class,
			'value' => [
				Field::NEUTRAL_LOCALE => [
					$this->textRow('b1', 'Hi', ['colspan' => 2, 'rowspan' => 1, 'col' => 1, 'row' => 1], $rowMeta),
				],
			],
			'meta' => $meta,
		];

		$this->assertTrue(
			$blocks
				->shape()
				->validate($content(
					['gap' => ['zxx' => 's'], 'rowGap' => ['zxx' => ''], 'other' => ['zxx' => 'kept']],
					['padding' => ['zxx' => 'xl'], 'class' => ['zxx' => 'hero']],
				))
				->valid(),
		);

		$result = $blocks
			->shape()
			->validate($content(
				['columnGap' => ['zxx' => 'huge']],
				['padding' => ['zxx' => 'huge']],
			));

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has(['meta', 'columnGap', 'zxx']));
		$this->assertTrue($result->has(['value', Field::NEUTRAL_LOCALE, 0, 'meta', 'padding', 'zxx']));
	}

	public function testAllowRejectsUnknownClasses(): void
	{
		$this->throws(RuntimeException::class, "allows unknown block type 'App\\Nope'");

		$this->createBlocks()->allow('App\\Nope');
	}

	public function testAllowRejectsClassesWithoutTheContract(): void
	{
		$this->throws(RuntimeException::class, 'must implement Cosray\\Contract\\Block');

		$this->createBlocks()->allow(TestEntry::class);
	}

	public function testBlockFieldsForRejectsDisallowedTypes(): void
	{
		$this->throws(RuntimeException::class, "Blocks field 'content' does not allow block type");

		$this
			->createBlocks()
			->allow(TextBlock::class)
			->blockFieldsFor(QuoteBlock::class);
	}

	public function testRejectsNestedBlocksFields(): void
	{
		$this->throws(RuntimeException::class, "cannot contain nested blocks field 'inner' in block type");

		$this->createBlocks()->allow(NestedBlocksBlock::class)->control();
	}

	public function testRejectsNestedEntriesFields(): void
	{
		$this->throws(RuntimeException::class, "cannot contain nested entries field 'inner' in block type");

		$this->createBlocks()->allow(NestedEntriesBlock::class)->control();
	}

	public function testFieldOrderAndHandleComeFromTheBlockType(): void
	{
		$blocks = $this->createBlocks()->allow(NoteBlock::class);

		$this->assertSame(['aside', 'body', 'cover'], array_keys($blocks->blockFields(NoteBlock::class)));
		$this->assertSame('note', $blocks->blockHandle(NoteBlock::class));
	}

	public function testStructureOfASharedList(): void
	{
		$structure = $this->createBlocks()->structure([
			$this->textRow(
				'b1',
				'Hello',
				['colspan' => 20, 'rowspan' => 0],
				['class' => ['zxx' => 'wide']],
			),
			['uid' => 'b2', 'type' => 'legacy', 'colspan' => 12],
			['uid' => 'b3', 'type' => Builtin\Heading::class, 'fields' => []],
		]);
		$rows = $structure['value'][Field::NEUTRAL_LOCALE];

		$this->assertSame(Blocks::class, $structure['type']);
		// A new value starts on the field's default grid.
		$this->assertSame(12, $structure['columns']);
		$this->assertArrayNotHasKey('meta', $structure);
		$this->assertCount(2, $rows);
		$this->assertSame('b1', $rows[0]['uid']);
		// Clamped to the grid, and placed: rows without a position flow in order.
		$this->assertSame(['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1], $rows[0]['layout']);
		$this->assertSame(['class' => ['zxx' => 'wide']], $rows[0]['meta']);
		$this->assertSame(Textarea::class, $rows[0]['fields']['text']['type']);
		$this->assertSame(['zxx' => 'Hello'], $rows[0]['fields']['text']['value']);
		// A fresh row carries every field of its type, defaults included.
		$this->assertSame(['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 2], $rows[1]['layout']);
		$this->assertSame(['zxx' => null], $rows[1]['fields']['text']['value']);
		$this->assertSame(['zxx' => '2'], $rows[1]['fields']['level']['value']);
		$this->assertArrayNotHasKey('meta', $rows[1]);
	}

	public function testStructureSkipsMalformedRows(): void
	{
		$structure = $this->createBlocks()->structure(['junk', 42, ['uid' => 'b1']]);

		$this->assertSame([Field::NEUTRAL_LOCALE => []], $structure['value']);
	}

	public function testStructureReadsTheStoredValueByDefault(): void
	{
		$structure = $this->createBlocks([
			'type' => Blocks::class,
			'value' => [Field::NEUTRAL_LOCALE => [$this->textRow('b1', 'Stored')]],
		])->structure();

		$this->assertSame('b1', $structure['value'][Field::NEUTRAL_LOCALE][0]['uid']);
	}

	public function testStructureOfPerLocaleLists(): void
	{
		$blocks = $this->createBlocks()->translate(TranslateMode::Asymmetric);

		$fromList = $blocks->structure([$this->textRow('b1', 'Hello')]);
		$fromMap = $blocks->structure(['de' => [$this->textRow('b2', 'Hallo')]]);

		$this->assertSame(['en', 'de'], array_keys($fromList['value']));
		$this->assertSame('b1', $fromList['value']['en'][0]['uid']);
		$this->assertSame([], $fromList['value']['de']);
		$this->assertSame([], $fromMap['value']['en']);
		$this->assertSame('b2', $fromMap['value']['de'][0]['uid']);
		// Sub-fields of a per-locale list are never translated themselves.
		$this->assertSame(['zxx' => 'Hallo'], $fromMap['value']['de'][0]['fields']['text']['value']);
	}

	public function testStructureOfASymmetricList(): void
	{
		$structure = $this
			->createBlocks()
			->translate()
			->structure([
				[
					'uid' => 'b1',
					'type' => TextBlock::class,
					'fields' => ['text' => ['type' => Textarea::class, 'value' => ['en' => 'Hello']]],
				],
			]);
		$text = $structure['value'][Field::NEUTRAL_LOCALE][0]['fields']['text']['value'];

		$this->assertSame(['en' => 'Hello', 'de' => null], $text);
	}

	public function testTranslationModeRuleOnSubFields(): void
	{
		$symmetric = $this
			->createBlocks()
			->translate()
			->allow(NoteBlock::class, TextBlock::class);
		$asymmetric = $this
			->createBlocks()
			->translate(TranslateMode::Asymmetric)
			->allow(NoteBlock::class, TextBlock::class);
		$untranslated = $this->createBlocks()->allow(NoteBlock::class, TextBlock::class);

		$this->assertSame(
			TranslateMode::Symmetric,
			$symmetric->blockFields(TextBlock::class)['text']->translateMode(),
		);
		$this->assertSame(
			TranslateMode::Asymmetric,
			$symmetric->blockFields(NoteBlock::class)['cover']->translateMode(),
		);
		$this->assertNull($asymmetric->blockFields(TextBlock::class)['text']->translateMode());
		$this->assertNull($asymmetric->blockFields(NoteBlock::class)['cover']->translateMode());
		$this->assertNull($untranslated->blockFields(TextBlock::class)['text']->translateMode());

		$properties = $untranslated->control()->array()['props']['blockTypes'][1]['fields'][0];
		$this->assertFalse($properties['translate']);
		$this->assertArrayNotHasKey('translateMode', $properties);
	}

	public function testTranslateNullResetsAField(): void
	{
		$text = new Text(
			'title',
			$this->createStub(\Cosray\Field\Owner::class),
			new \Cosray\Value\ValueContext('title', []),
		);

		$this->assertTrue($text->translate()->isTranslatable());
		$this->assertFalse($text->translate(null)->isTranslatable());
	}

	public function testToolsFeedRichtextSubFieldsWithoutTheirOwn(): void
	{
		$blocks = $this->createBlocks()->allow(NoteBlock::class, Builtin\RichText::class);
		$blocks->tools(Tool::H1, Tool::Bold);
		$note = $blocks->blockFields(NoteBlock::class);
		$richtext = $blocks->blockFields(Builtin\RichText::class)['text'];

		$this->assertInstanceOf(RichText::class, $richtext);
		$this->assertSame(['h1', 'bold'], $richtext->getTools());
		$this->assertSame(['h1', 'bold'], $note['body']->getTools());
		$this->assertSame(['bold', 'italic', 'link'], $note['aside']->getTools());

		// Without a list from the field, a block's richtext gets the inline
		// preset rather than the full toolbar's default.
		$plain = $this->createBlocks()->allow(NoteBlock::class)->blockFields(NoteBlock::class);
		$this->assertSame(
			array_map(static fn(Tool $tool): string => $tool->value, Tool::INLINE),
			$plain['body']->getTools(),
		);
		$this->assertSame(['bold', 'italic', 'link'], $plain['aside']->getTools());
	}

	public function testShapeAcceptsValidRows(): void
	{
		$result = $this
			->createBlocks()
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [
					Field::NEUTRAL_LOCALE => [
						$this->textRow(
							'b1',
							'Hello',
							['colspan' => '6', 'rowspan' => 1, 'col' => 7, 'row' => 1],
							['class' => ['zxx' => 'x']],
						),
						[
							'uid' => 'b2',
							'type' => Builtin\Heading::class,
							'layout' => ['colspan' => 2, 'rowspan' => 6, 'col' => 1, 'row' => 1],
							'fields' => [
								'text' => ['type' => Text::class, 'value' => ['zxx' => 'Title']],
								'level' => ['type' => \Cosray\Field\Option::class, 'value' => ['zxx' => '3']],
							],
						],
					],
				],
			]);

		$this->assertTrue($result->valid(), json_encode($result->issues()));
		$rows = $result->values()['value'][Field::NEUTRAL_LOCALE];
		$this->assertSame(['colspan' => 6, 'rowspan' => 1, 'col' => 7, 'row' => 1], $rows[0]['layout']);
		$this->assertSame(['class' => ['zxx' => 'x']], $rows[0]['meta']);
		$this->assertSame('Title', $rows[1]['fields']['text']['value']['zxx']);
	}

	public function testShapeRejectsUnknownTypesAndUnknownRows(): void
	{
		$result = $this
			->createBlocks()
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [
					Field::NEUTRAL_LOCALE => [
						[
							'uid' => 'b1',
							'type' => 'richtext',
							'layout' => ['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1],
							'fields' => [],
						],
						[
							'type' => TextBlock::class,
							'layout' => ['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 2],
							'fields' => [],
						],
					],
				],
			]);

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has(['value', Field::NEUTRAL_LOCALE, 0, 'type']));
		$this->assertTrue($result->has(['value', Field::NEUTRAL_LOCALE, 1, 'uid']));
	}

	public function testShapeRejectsMalformedRows(): void
	{
		$shape = $this->createBlocks()->shape();
		$rows = static fn(array $rows): array => ['type' => Blocks::class, 'value' => [Field::NEUTRAL_LOCALE => $rows]];

		$this->assertFalse($shape->validate($rows(['junk']))->valid());
		$this->assertFalse(
			$shape
				->validate($rows([
					['uid' => 'b1', 'type' => TextBlock::class, 'layout' => 'junk', 'fields' => 'junk'],
				]))
				->valid(),
		);
		$this->assertFalse(
			$shape
				->validate($rows([
					[
						'uid' => 'b1',
						'type' => 'App\\Nope',
						'layout' => ['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1],
						'fields' => [],
					],
				]))
				->valid(),
		);
	}

	public function testShapeRejectsLayoutOutsideTheGrid(): void
	{
		$shape = $this->createBlocks()->shape();
		$row = fn(array $layout, ?int $columns = null): array => [
			'type' => Blocks::class,
			...($columns === null ? [] : ['columns' => $columns]),
			'value' => [Field::NEUTRAL_LOCALE => [$this->textRow('b1', 'Hello', $layout)]],
		];
		$path = static fn(string $key, int $index = 0): array => [
			'value',
			Field::NEUTRAL_LOCALE,
			$index,
			'layout',
			$key,
		];
		$at = static fn(int $colspan, int $col, int $rowspan = 1): array => [
			'colspan' => $colspan,
			'rowspan' => $rowspan,
			'col' => $col,
			'row' => 1,
		];

		$this->assertTrue($shape->validate($row($at(13, 1)))->has($path('colspan')));
		$this->assertTrue($shape->validate($row($at(1, 1)))->has($path('colspan')));
		$this->assertTrue($shape->validate($row($at(12, 1, 7)))->has($path('rowspan')));
		$this->assertTrue($shape->validate($row($at(12, 1, 0)))->has($path('rowspan')));
		// A position is optional, but not half of one.
		$this->assertTrue($shape->validate($row(['colspan' => 6, 'rowspan' => 1]))->valid());
		$this->assertTrue($shape->validate($row(['colspan' => 6, 'rowspan' => 1, 'col' => 1]))->has($path('col')));
		$this->assertTrue($shape->validate($row($at(6, 8)))->has($path('col')));
		$this->assertTrue($shape->validate($row($at(6, 7)))->valid());
		// The bounds are the value's own grid, not the field's default.
		$this->assertTrue($shape->validate($row($at(8, 1), 6))->has($path('colspan')));
		$this->assertTrue($shape->validate($row($at(6, 1), 6))->valid());
		$this->assertTrue($shape->validate($row($at(6, 1), 30))->has(['columns']));
	}

	public function testShapeRejectsOverlappingBlocks(): void
	{
		$shape = $this->createBlocks()->shape();
		$rows = fn(array ...$layouts): array => [
			'type' => Blocks::class,
			'value' => [
				Field::NEUTRAL_LOCALE => array_map(
					fn(int $index): array => $this->textRow("b{$index}", 'x', $layouts[$index]),
					array_keys($layouts),
				),
			],
		];
		$tall = ['colspan' => 6, 'rowspan' => 2, 'col' => 1, 'row' => 1];

		$this->assertTrue($shape->validate($rows($tall, [
			'colspan' => 6,
			'rowspan' => 1,
			'col' => 3,
			'row' => 2,
		]))->has([
			'value',
			Field::NEUTRAL_LOCALE,
			1,
			'layout',
			'col',
		]));
		$this->assertTrue(
			$shape->validate($rows($tall, ['colspan' => 6, 'rowspan' => 1, 'col' => 7, 'row' => 2]))->valid(),
		);
	}

	public function testShapeReportsSubFieldIssuesWithTheRowPath(): void
	{
		$result = $this
			->createBlocks()
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [Field::NEUTRAL_LOCALE => [$this->textRow('b1', '', [
						'colspan' => 12,
						'rowspan' => 1,
						'col' => 1,
						'row' => 1,
					])]],
			]);

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has(['value', Field::NEUTRAL_LOCALE, 0, 'fields', 'text', 'value', 'zxx']));
	}

	public function testShapeOfPerLocaleListsRequiresTheDefaultLocaleOnly(): void
	{
		$blocks = $this->createBlocks()->translate(TranslateMode::Asymmetric);
		$blocks->required();
		$shape = $blocks->shape();
		$row = $this->textRow('b1', 'Hello', ['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1]);

		$this->assertTrue($shape->validate(['type' => Blocks::class, 'value' => ['en' => [$row]]])->valid());
		$this->assertTrue(
			$shape->validate(['type' => Blocks::class, 'value' => ['en' => [$row], 'de' => null]])->valid(),
		);
		$this->assertFalse($shape->validate(['type' => Blocks::class, 'value' => ['de' => [$row]]])->valid());
	}

	public function testShapeOfASymmetricListValidatesTranslatedSubFields(): void
	{
		$blocks = $this->createBlocks()->translate();
		$result = $blocks
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [
					Field::NEUTRAL_LOCALE => [
						[
							'uid' => 'b1',
							'type' => TextBlock::class,
							'layout' => ['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1],
							'fields' => [
								'text' => ['type' => Textarea::class, 'value' => ['en' => 'Hello', 'de' => null]],
							],
						],
					],
				],
			]);

		$this->assertTrue($result->valid(), json_encode($result->issues()));
		$this->assertSame(
			['en' => 'Hello', 'de' => null],
			$result->values()['value'][Field::NEUTRAL_LOCALE][0]['fields']['text']['value'],
		);
	}

	public function testMediaSubFieldsUseTheirOwnShape(): void
	{
		$result = $this
			->createBlocks()
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [
					Field::NEUTRAL_LOCALE => [
						[
							'uid' => 'b1',
							'type' => Builtin\Image::class,
							'layout' => ['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1],
							'fields' => ['image' => ['type' => Image::class, 'value' => ['zxx' => [['meta' => []]]]]],
						],
					],
				],
			]);

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has([
			'value',
			Field::NEUTRAL_LOCALE,
			0,
			'fields',
			'image',
			'value',
			'zxx',
			0,
			'uid',
		]));
	}

	public function testStructureOfASplit(): void
	{
		$rows = $this->createBlocks()->structure([
			[
				...$this->split(
					[
						$this->textRow('b1', 'Left', ['colspan' => 12, 'rowspan' => 4]),
						['uid' => 'b2', 'type' => 'legacy'],
						'junk',
						$this->textRow('b3', 'Right', ['colspan' => 3, 'rowspan' => 2]),
					],
					['colspan' => 6, 'rowspan' => 2, 'col' => 7, 'row' => 1],
				),
				'meta' => ['class' => ['zxx' => 'pair']],
			],
		])['value'][Field::NEUTRAL_LOCALE];

		$this->assertSame(['uid', 'layout', 'blocks', 'meta'], array_keys($rows[0]));
		$this->assertSame(['class' => ['zxx' => 'pair']], $rows[0]['meta']);
		$this->assertSame(['colspan' => 6, 'rowspan' => 2, 'col' => 7, 'row' => 1], $rows[0]['layout']);
		$this->assertSame(['b1', 'b3'], array_column($rows[0]['blocks'], 'uid'));
		// Clamped into the split's area, not the field's, and without a position of its own.
		$this->assertSame(['colspan' => 6, 'rowspan' => 2], $rows[0]['blocks'][0]['layout']);
		$this->assertSame(['zxx' => 'Right'], $rows[0]['blocks'][1]['fields']['text']['value']);
	}

	public function testStructureDissolvesASplitLeftWithOneBlock(): void
	{
		$rows = $this->createBlocks()->structure([
			$this->split(
				[
					$this->textRow('b1', 'Kept', ['colspan' => 3, 'rowspan' => 1]),
					['uid' => 'b2', 'type' => 'legacy'],
				],
				['colspan' => 6, 'rowspan' => 1, 'col' => 3, 'row' => 1],
			),
			$this->split(
				[['uid' => 'b3', 'type' => 'legacy']],
				['colspan' => 6, 'rowspan' => 1, 'col' => 1, 'row' => 2],
				's2',
			),
		])['value'][Field::NEUTRAL_LOCALE];

		$this->assertCount(1, $rows);
		$this->assertSame('b1', $rows[0]['uid']);
		$this->assertSame(TextBlock::class, $rows[0]['type']);
		$this->assertSame(['colspan' => 6, 'rowspan' => 1, 'col' => 3, 'row' => 1], $rows[0]['layout']);
	}

	public function testShapeAcceptsSplitsInBothDirections(): void
	{
		$column = ['colspan' => 3, 'rowspan' => 2];
		$row = ['colspan' => 6, 'rowspan' => 1];
		$result = $this
			->createBlocks()
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [
					Field::NEUTRAL_LOCALE => [
						$this->split(
							[$this->textRow('b1', 'Left', $column), $this->textRow('b2', 'Right', $column)],
							['colspan' => 6, 'rowspan' => 2, 'col' => 1, 'row' => 1],
						),
						$this->split(
							[
								$this->textRow('b3', 'Top', $row),
								$this->textRow('b4', 'Middle', $row),
								$this->textRow('b5', 'Bottom', $row),
							],
							['colspan' => 6, 'rowspan' => 3, 'col' => 7, 'row' => 1],
							's2',
						),
					],
				],
			]);

		$this->assertTrue($result->valid(), json_encode($result->issues()));
		$rows = $result->values()['value'][Field::NEUTRAL_LOCALE];
		$this->assertSame(['b1', 'b2'], array_column($rows[0]['blocks'], 'uid'));
		$this->assertSame('Bottom', $rows[1]['blocks'][2]['fields']['text']['value']['zxx']);
	}

	public function testShapeRejectsRowsThatAreNeitherBlockNorSplit(): void
	{
		$shape = $this->createBlocks()->shape();
		$half = ['colspan' => 6, 'rowspan' => 1];
		$wide = ['colspan' => 12, 'rowspan' => 1];
		$full = [...$wide, 'col' => 1, 'row' => 1];
		$rows = static fn(array ...$rows): array => [
			'type' => Blocks::class,
			'value' => [Field::NEUTRAL_LOCALE => $rows],
		];
		$kind = ['value', Field::NEUTRAL_LOCALE, 0];

		// One block is no split.
		$this->assertTrue($shape->validate($rows($this->split([$this->textRow('b1', 'x', $wide)], $full)))->has($kind));
		// A type or fields beside the blocks, or neither.
		$both = [...$this->split([$this->textRow('b1', 'x', $half), $this->textRow('b2', 'y', $half)], $full)];
		$this->assertTrue($shape->validate($rows([...$both, 'type' => TextBlock::class, 'fields' => []]))->has(
			$kind,
		));
		$this->assertTrue($shape->validate($rows([...$both, 'fields' => []]))->has($kind));
		$this->assertTrue($shape->validate($rows(['uid' => 'b1', 'layout' => $full]))->has($kind));
		$this->assertTrue($shape->validate($rows([
			'uid' => 'b1',
			'type' => TextBlock::class,
			'layout' => $full,
		]))->has($kind));
		// A block of a split is never split again.
		$nested = $this->split([$this->textRow('b3', 'x', $half), $this->textRow('b4', 'y', $half)], $half, 's2');
		$this->assertTrue(
			$shape
				->validate($rows($this->split([
					[...$this->textRow('b1', 'x', $half), ...$nested],
					$this->textRow('b2', 'y', $half),
				], $full)))
				->has(['value', Field::NEUTRAL_LOCALE, 0, 'blocks', 0, 'blocks']),
		);
		$this->assertTrue($shape->validate($rows($both))->valid());
	}

	public function testShapeRejectsBlocksOverflowingTheirSplit(): void
	{
		$shape = $this->createBlocks()->shape();
		$validate = fn(array $area, array ...$layouts) => $shape->validate([
			'type' => Blocks::class,
			'value' => [
				Field::NEUTRAL_LOCALE => [
					$this->split(
						array_map(fn(int $i): array => $this->textRow(
							"b{$i}",
							'x',
							$layouts[$i],
						), array_keys($layouts)),
						$area,
					),
				],
			],
		]);
		$area = static fn(int $colspan, int $rowspan): array => [
			'colspan' => $colspan,
			'rowspan' => $rowspan,
			'col' => 1,
			'row' => 1,
		];
		$block = static fn(int $colspan, int $rowspan): array => ['colspan' => $colspan, 'rowspan' => $rowspan];
		$overflow = static fn(int $index): array => ['value', Field::NEUTRAL_LOCALE, 0, 'blocks', $index, 'layout'];

		// Wider than the split, or wrapping onto a row it does not have.
		$this->assertTrue($validate($area(6, 1), $block(8, 1), $block(2, 1))->has([...$overflow(0), 'colspan']));
		$this->assertTrue($validate($area(6, 1), $block(4, 1), $block(4, 1))->has($overflow(1)));
		// Taller than the split, or wrapping past its last row.
		$this->assertTrue($validate($area(6, 1), $block(3, 2), $block(3, 1))->has($overflow(0)));
		$this->assertTrue($validate($area(6, 2), $block(6, 1), $block(6, 1), $block(6, 1))->has($overflow(2)));
		// The flow fills the cell a taller neighbour leaves free.
		$this->assertTrue($validate($area(6, 2), $block(3, 2), $block(3, 1), $block(3, 1))->valid());
		$this->assertTrue($validate($area(6, 2), $block(2, 2), $block(4, 1), $block(4, 1))->valid());
	}

	public function testShapeReportsSplitBlockIssuesWithTheirPath(): void
	{
		$half = ['colspan' => 6, 'rowspan' => 1];
		$result = $this
			->createBlocks()
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [
					Field::NEUTRAL_LOCALE => [
						$this->split(
							[$this->textRow('b1', 'Filled', $half), $this->textRow('b2', '', $half)],
							['colspan' => 12, 'rowspan' => 1, 'col' => 1, 'row' => 1],
						),
					],
				],
			]);

		$this->assertFalse($result->valid());
		$this->assertTrue($result->has([
			'value',
			Field::NEUTRAL_LOCALE,
			0,
			'blocks',
			1,
			'fields',
			'text',
			'value',
			'zxx',
		]));
	}
}

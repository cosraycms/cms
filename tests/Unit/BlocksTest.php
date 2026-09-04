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
		$blocks->init(Services::withDefaults());
		$blocks->columns($columns, $min);

		return $blocks;
	}

	private function textRow(string $uid, string $text, array $layout = [], array $meta = []): array
	{
		$row = [
			'uid' => $uid,
			'type' => Builtin\Text::class,
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

	public function testBlocksFieldCreation(): void
	{
		$blocks = $this->createBlocks();

		$this->assertInstanceOf(BlocksValue::class, $blocks->value());
		$this->assertSame(Registry::withDefaults()->all(), $blocks->allowedBlockTypes());
		$this->assertSame(['text', 'level'], array_keys($blocks->blockFields(Builtin\Heading::class)));
		$this->assertSame(['text'], array_keys($blocks->blockFields()));
		$this->assertTrue($blocks->allows(Builtin\Image::class));
		$this->assertFalse($blocks->allows(QuoteBlock::class));
	}

	public function testControlCarriesBlockTypesAndGrid(): void
	{
		Verba::activate(new Translator('de', ['cosray' => self::root() . '/lang']));

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
		$this->assertSame('Formatierter Text', $types[Builtin\RichText::class]['label']);
		$this->assertSame('heading', $types[Builtin\Heading::class]['handle']);
		$this->assertSame('Überschrift', $types[Builtin\Heading::class]['label']);
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
		$blocks->init(Services::withDefaults());
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
		$blocks = $this->createBlocks()->allow(QuoteBlock::class, Builtin\Text::class);
		$types = array_column($blocks->control()->array()['props']['blockTypes'], 'handle', 'type');

		$this->assertSame([QuoteBlock::class => 'quote-block', Builtin\Text::class => 'text'], $types);
		$this->assertTrue($blocks->allows(QuoteBlock::class));
		$this->assertFalse($blocks->allows(Builtin\Image::class));
		$this->assertSame(['text', 'source'], array_keys($blocks->blockFields(QuoteBlock::class)));
	}

	public function testASingleFieldBlockDropsItsSubFieldLabel(): void
	{
		$blocks = $this->createBlocks()->allow(
			Builtin\Text::class,
			QuoteBlock::class,
			LabelledBlock::class,
		);
		$types = array_column($blocks->control()->array()['props']['blockTypes'], 'labels', 'type');

		// One field: the block's own label already names it.
		$this->assertFalse($types[Builtin\Text::class]);
		// Two fields say different things, so both keep their labels.
		$this->assertTrue($types[QuoteBlock::class]);
		// One field, but the type asked for the label back.
		$this->assertTrue($types[LabelledBlock::class]);
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
			->allow(Builtin\Text::class)
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
			$this->textRow('b1', 'Hello', ['span' => 20, 'rows' => 0, 'indent' => 5], ['class' => ['zxx' => 'wide']]),
			['uid' => 'b2', 'type' => 'legacy', 'colspan' => 12],
			['uid' => 'b3', 'type' => Builtin\Heading::class, 'fields' => []],
		]);
		$rows = $structure['value'][Field::NEUTRAL_LOCALE];

		$this->assertSame(Blocks::class, $structure['type']);
		$this->assertArrayNotHasKey('meta', $structure);
		$this->assertCount(2, $rows);
		$this->assertSame('b1', $rows[0]['uid']);
		$this->assertSame(['span' => 12, 'rows' => 1, 'indent' => 0], $rows[0]['layout']);
		$this->assertSame(['class' => ['zxx' => 'wide']], $rows[0]['meta']);
		$this->assertSame(Textarea::class, $rows[0]['fields']['text']['type']);
		$this->assertSame(['zxx' => 'Hello'], $rows[0]['fields']['text']['value']);
		// A fresh row carries every field of its type, defaults included.
		$this->assertSame(['span' => 12, 'rows' => 1, 'indent' => 0], $rows[1]['layout']);
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
					'type' => Builtin\Text::class,
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
			->allow(NoteBlock::class, Builtin\Text::class);
		$asymmetric = $this
			->createBlocks()
			->translate(TranslateMode::Asymmetric)
			->allow(NoteBlock::class, Builtin\Text::class);
		$untranslated = $this->createBlocks()->allow(NoteBlock::class, Builtin\Text::class);

		$this->assertSame(
			TranslateMode::Symmetric,
			$symmetric->blockFields(Builtin\Text::class)['text']->translateMode(),
		);
		$this->assertSame(
			TranslateMode::Asymmetric,
			$symmetric->blockFields(NoteBlock::class)['cover']->translateMode(),
		);
		$this->assertNull($asymmetric->blockFields(Builtin\Text::class)['text']->translateMode());
		$this->assertNull($asymmetric->blockFields(NoteBlock::class)['cover']->translateMode());
		$this->assertNull($untranslated->blockFields(Builtin\Text::class)['text']->translateMode());

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

		$plain = $this->createBlocks()->allow(NoteBlock::class)->blockFields(NoteBlock::class);
		$this->assertSame(
			array_map(static fn(Tool $tool): string => $tool->value, Tool::DEFAULT),
			$plain['body']->getTools(),
		);
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
							['span' => '6', 'rows' => 1, 'indent' => 6],
							['class' => ['zxx' => 'x']],
						),
						[
							'uid' => 'b2',
							'type' => Builtin\Heading::class,
							'layout' => ['span' => 2, 'rows' => 6, 'indent' => 0],
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
		$this->assertSame(['span' => 6, 'rows' => 1, 'indent' => 6], $rows[0]['layout']);
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
							'layout' => ['span' => 12, 'rows' => 1, 'indent' => 0],
							'fields' => [],
						],
						[
							'type' => Builtin\Text::class,
							'layout' => ['span' => 12, 'rows' => 1, 'indent' => 0],
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
					['uid' => 'b1', 'type' => Builtin\Text::class, 'layout' => 'junk', 'fields' => 'junk'],
				]))
				->valid(),
		);
		$this->assertFalse(
			$shape
				->validate($rows([
					[
						'uid' => 'b1',
						'type' => 'App\\Nope',
						'layout' => ['span' => 12, 'rows' => 1, 'indent' => 0],
						'fields' => [],
					],
				]))
				->valid(),
		);
	}

	public function testShapeRejectsLayoutOutsideTheGrid(): void
	{
		$shape = $this->createBlocks()->shape();
		$row = fn(array $layout): array => [
			'type' => Blocks::class,
			'value' => [Field::NEUTRAL_LOCALE => [$this->textRow('b1', 'Hello', $layout)]],
		];
		$path = static fn(string $key): array => ['value', Field::NEUTRAL_LOCALE, 0, 'layout', $key];

		$this->assertTrue($shape->validate($row(['span' => 13, 'rows' => 1, 'indent' => 0]))->has($path('span')));
		$this->assertTrue($shape->validate($row(['span' => 1, 'rows' => 1, 'indent' => 0]))->has($path('span')));
		$this->assertTrue($shape->validate($row(['span' => 12, 'rows' => 7, 'indent' => 0]))->has($path('rows')));
		$this->assertTrue($shape->validate($row(['span' => 12, 'rows' => 0, 'indent' => 0]))->has($path('rows')));
		$this->assertTrue($shape->validate($row(['span' => 6, 'rows' => 1, 'indent' => 11]))->has($path('indent')));
		$this->assertTrue($shape->validate($row(['span' => 6, 'rows' => 1, 'indent' => 7]))->has($path('indent')));
		$this->assertTrue($shape->validate($row(['span' => 6, 'rows' => 1]))->has($path('indent')));
		$this->assertTrue($shape->validate($row(['span' => 6, 'rows' => 1, 'indent' => 6]))->valid());
	}

	public function testShapeReportsSubFieldIssuesWithTheRowPath(): void
	{
		$result = $this
			->createBlocks()
			->shape()
			->validate([
				'type' => Blocks::class,
				'value' => [Field::NEUTRAL_LOCALE => [$this->textRow('b1', '', [
						'span' => 12,
						'rows' => 1,
						'indent' => 0,
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
		$row = $this->textRow('b1', 'Hello', ['span' => 12, 'rows' => 1, 'indent' => 0]);

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
							'type' => Builtin\Text::class,
							'layout' => ['span' => 12, 'rows' => 1, 'indent' => 0],
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
							'layout' => ['span' => 12, 'rows' => 1, 'indent' => 0],
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
}

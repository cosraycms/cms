<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Container\Container;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Block\Registry;
use Cosray\Block\RenderContext;
use Cosray\Block\Types;
use Cosray\Contract\Block as BlockType;
use Cosray\Exception\RuntimeException;
use Cosray\Field;
use Cosray\Field\Blocks;
use Cosray\Field\Owner;
use Cosray\Field\Services;
use Cosray\Richtext\Envelope;
use Cosray\Tests\Fixtures\Block\NoteBlock;
use Cosray\Tests\Fixtures\Block\QuoteBlock;
use Cosray\Tests\Fixtures\Block\ServiceBlock;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\RichtextOwnerTestCase;
use Cosray\Value\Block;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlockTypesTest extends RichtextOwnerTestCase
{
	public function testDefaultRegistryContents(): void
	{
		$this->assertSame(
			[
				Types\RichText::class,
				Types\Text::class,
				Types\Heading::class,
				Types\Image::class,
				Types\Images::class,
				Types\Video::class,
				Types\Youtube::class,
				Types\Iframe::class,
			],
			Registry::withDefaults()->all(),
		);
	}

	public function testRegisterAddsOnceAndRejectsOtherClasses(): void
	{
		$registry = Registry::withDefaults();
		$registry->register(QuoteBlock::class);
		$registry->register(QuoteBlock::class);

		$this->assertTrue($registry->has(QuoteBlock::class));
		$this->assertSame(1, count(array_keys($registry->all(), QuoteBlock::class, true)));

		$this->throws(RuntimeException::class, 'Block types must implement Cosray\\Contract\\Block');
		$registry->register(TestEntry::class);
	}

	public function testCreateAutowiresBlockTypes(): void
	{
		$owner = $this->owner(['app.name' => 'Acme']);
		$type = Registry::withDefaults()->create(ServiceBlock::class, $owner);

		$this->assertInstanceOf(ServiceBlock::class, $type);
		$this->assertSame('Acme: Hi', $type->render($this->block($owner, ServiceBlock::class, [
			'text' => ['type' => Field\Text::class, 'value' => ['zxx' => 'Hi']],
		]), $this->context($owner)));
	}

	public function testCreateRejectsContainerServices(): void
	{
		$container = new Container();
		$container->add(QuoteBlock::class, new QuoteBlock());
		$registry = Registry::withDefaults();
		$registry->useContainer($container);

		$this->throws(RuntimeException::class, 'must not be registered as a container service');
		$registry->create(QuoteBlock::class, $this->owner());
	}

	public function testCreateRejectsOtherClasses(): void
	{
		$this->throws(RuntimeException::class, 'Block types must implement');

		Registry::withDefaults()->create(TestEntry::class, $this->owner());
	}

	public function testHandlesAndLabels(): void
	{
		$field = $this->field($this->owner())->allow(...[
			...Registry::withDefaults()->all(),
			QuoteBlock::class,
			NoteBlock::class,
		]);
		$handles = [];

		foreach ($field->allowedBlockTypes() as $type) {
			$handles[$type] = $field->blockHandle($type);
		}

		$this->assertSame(
			[
				Types\RichText::class => 'richtext',
				Types\Text::class => 'text',
				Types\Heading::class => 'heading',
				Types\Image::class => 'image',
				Types\Images::class => 'images',
				Types\Video::class => 'video',
				Types\Youtube::class => 'youtube',
				Types\Iframe::class => 'iframe',
				QuoteBlock::class => 'quote-block',
				ServiceBlock::class => 'service-block',
				NoteBlock::class => 'note',
			],
			$handles,
		);

		Verba::activate(new Translator('de', ['cosray' => self::root() . '/lang']));

		try {
			$labels = array_column($field->control()->array()['props']['blockTypes'], 'label', 'handle');
		} finally {
			Verba::deactivate();
		}

		$this->assertSame('Formatierter Text', $labels['richtext']);
		$this->assertSame('Einfacher Text', $labels['text']);
		$this->assertSame('Überschrift', $labels['heading']);
		$this->assertSame('Quote', $labels['quote-block']);
	}

	public function testRenderContextResolvesLocaleMaps(): void
	{
		$ctx = $this->context($this->owner());

		$this->assertSame('english', $ctx->effective(['en' => 'english', 'zxx' => 'neutral']));
		$this->assertSame('neutral', $ctx->effective(['en' => '', 'zxx' => 'neutral']));
		$this->assertNull($ctx->effective(['en' => [], 'de' => 'german']));
		$this->assertSame('cms', $ctx->prefix());
		$this->assertSame('div', $ctx->tag());
		$this->assertSame('', $ctx->class());
	}

	public function testTextRendersEscapedWithLineBreaks(): void
	{
		$this->assertSame(
			"a &lt;b&gt;<br />\nc",
			$this->render(Types\Text::class, [
				'text' => ['type' => Field\Textarea::class, 'value' => ['zxx' => "a <b>\nc"]],
			]),
		);
	}

	public function testRenderResolvesTheLocaleFallback(): void
	{
		$owner = $this->owner();
		$field = $this->field($owner)->translate();

		$this->assertSame('fallback', $this->render(
			Types\Text::class,
			[
				'text' => ['type' => Field\Textarea::class, 'value' => ['de' => '', 'en' => 'fallback']],
			],
			$field,
		));
	}

	public function testHeadingRendersTheLevel(): void
	{
		$heading = fn(?string $level): string => $this->render(Types\Heading::class, [
			'text' => ['type' => Field\Text::class, 'value' => ['zxx' => 'Hi <there>']],
			'level' => ['type' => Field\Option::class, 'value' => ['zxx' => $level]],
		]);

		$this->assertSame('<h3>Hi &lt;there&gt;</h3>', $heading('3'));
		$this->assertSame('<h2>Hi &lt;there&gt;</h2>', $heading(null));
		$this->assertSame('<h6>Hi &lt;there&gt;</h6>', $heading('9'));
		$this->assertSame('<h1>Hi &lt;there&gt;</h1>', $heading('-2'));
	}

	public function testRichtextRendersStructuredDocuments(): void
	{
		$doc = [
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [['type' => 'text', 'text' => 'fett', 'marks' => [['type' => 'bold']]]],
				],
			],
		];

		$this->assertSame('<p><strong>fett</strong></p>', $this->render(Types\RichText::class, [
			'text' => [
				'type' => Field\RichText::class,
				'format' => Envelope::FORMAT,
				'version' => Envelope::VERSION,
				'value' => ['zxx' => $doc],
			],
		]));
		// Unmigrated legacy HTML does not render.
		$this->assertSame('', $this->render(Types\RichText::class, [
			'text' => ['type' => Field\RichText::class, 'value' => ['zxx' => '<p>alt</p>']],
		]));
	}

	public function testYoutubeRendersTheEmbed(): void
	{
		$html = $this->render(Types\Youtube::class, [
			'video' => [
				'type' => Field\Youtube::class,
				'value' => ['zxx' => 'dQw4w9WgXcQ'],
				'meta' => ['aspectRatioX' => ['zxx' => 4], 'aspectRatioY' => ['zxx' => 3]],
			],
		]);

		$this->assertStringContainsString('src="https://www.youtube.com/embed/dQw4w9WgXcQ"', $html);
		$this->assertStringContainsString('padding-top: 75.00%', $html);
		$this->assertSame('', $this->render(Types\Youtube::class, [
			'video' => ['type' => Field\Youtube::class, 'value' => ['zxx' => '']],
		]));
	}

	public function testIframeRendersRaw(): void
	{
		$code = '<iframe src="https://example.com/embed"></iframe>';

		$this->assertSame($code, $this->render(Types\Iframe::class, [
			'code' => ['type' => Field\Iframe::class, 'value' => ['zxx' => $code]],
		]));
	}

	public function testQuoteBlockRenders(): void
	{
		$this->assertSame(
			'<blockquote><p>Less</p><cite>Mies</cite></blockquote>',
			$this->render(QuoteBlock::class, [
				'text' => ['type' => Field\Textarea::class, 'value' => ['zxx' => 'Less']],
				'source' => ['type' => Field\Text::class, 'value' => ['zxx' => 'Mies']],
			]),
		);
	}

	private function render(string $type, array $fields, ?Blocks $field = null): string
	{
		$field ??= $this->field($this->owner());
		$owner = $field->owner;
		$instance = Registry::withDefaults()->create($type, $owner);
		$this->assertInstanceOf(BlockType::class, $instance);

		return $instance->render($this->block($owner, $type, $fields, $field), $this->context($owner));
	}

	private function block(Owner $owner, string $type, array $fields, ?Blocks $field = null): Block
	{
		$field ??= $this->field($owner);

		return new Block(
			$owner,
			$field,
			new ValueContext('content', ['uid' => 'b1', 'type' => $type, 'fields' => $fields]),
			$type,
		);
	}

	private function field(Owner $owner): Blocks
	{
		$field = new Blocks('content', $owner, new ValueContext('content', []));
		$field->init(Services::withDefaults());
		$field->columns(12)->allow(...[...Registry::withDefaults()->all(), QuoteBlock::class, ServiceBlock::class]);

		return $field;
	}

	private function context(Owner $owner): RenderContext
	{
		return new RenderContext($owner, 'content', 12, []);
	}
}

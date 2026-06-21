<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\Request;
use Celemas\Sire\Shape;
use Cosray\Config;
use Cosray\Field\Blocks;
use Cosray\Field\Checkbox;
use Cosray\Field\Code;
use Cosray\Field\Date;
use Cosray\Field\Entries;
use Cosray\Field\Field;
use Cosray\Field\FieldHydrator;
use Cosray\Field\File;
use Cosray\Field\Iframe;
use Cosray\Field\Image;
use Cosray\Field\Number;
use Cosray\Field\Option;
use Cosray\Field\Owner;
use Cosray\Field\RichText;
use Cosray\Field\Schema\Registry;
use Cosray\Field\Textarea;
use Cosray\Field\Time;
use Cosray\Field\Video;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Panel\FieldRenderer;
use Cosray\Renderer;
use Cosray\Schema\Description;
use Cosray\Schema\Hidden;
use Cosray\Schema\Label;
use Cosray\Schema\Options;
use Cosray\Schema\Required;
use Cosray\Schema\Syntax;
use Cosray\Tests\Fixtures\Field\TestCode;
use Cosray\Tests\Fixtures\Field\TestText;
use Cosray\Tests\TestCase;
use Cosray\Validation\Shapes;
use Cosray\Value\Value;
use Cosray\Value\ValueContext;
use Cosray\View\Boiler\Renderer as BoilerRenderer;
use Override;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelFieldRendererTest extends TestCase
{
	public function testResolvesSpecificTemplateBeforeParentTemplate(): void
	{
		$renderer = new FieldRenderer($this->view());

		$this->assertSame('field/code', $renderer->template($this->field(TestCode::class, 'body')));
	}

	public function testResolvesParentTemplateForFieldSubclasses(): void
	{
		$renderer = new FieldRenderer($this->view());

		$this->assertSame('field/text', $renderer->template($this->field(TestText::class, 'title')));
	}

	public function testReturnsUnsupportedTemplateForUnknownField(): void
	{
		$renderer = new FieldRenderer($this->view());

		$this->assertSame('field/unsupported', $renderer->template($this->unknownField()));
	}

	public function testResolvesJsonFieldTemplates(): void
	{
		$renderer = new FieldRenderer($this->view());

		$this->assertSame('field/file', $renderer->template($this->field(File::class, 'document')));
		$this->assertSame('field/image', $renderer->template($this->field(Image::class, 'gallery')));
		$this->assertSame('field/video', $renderer->template($this->field(Video::class, 'clip')));
		$this->assertSame('field/blocks', $renderer->template($this->field(Blocks::class, 'blocks')));
		$this->assertSame('field/entries', $renderer->template($this->field(Entries::class, 'entries')));
	}

	public function testBuildsNormalizedContextFromFieldMetadata(): void
	{
		$node = new class {
			#[Label('Hero title')]
			#[Description('Shown to visitors')]
			#[Required]
			#[Hidden]
			public TestText $hero_title;
		};
		$hydrator = new FieldHydrator(Registry::withDefaults());
		$hydrator->hydrate(
			$node,
			[
				'hero_title' => [
					'value' => ['zxx' => 'Saved title'],
				],
			],
			$this->owner(),
		);
		$field = $hydrator->getField($node, 'hero_title');
		$context = new FieldRenderer($this->view())->context($field);

		$this->assertSame('hero_title', $context['name']);
		$this->assertSame(TestText::class, $context['type']);
		$this->assertSame('hero_title', $context['id']);
		$this->assertSame('field-hero_title', $context['inputId']);
		$this->assertSame('Hero title', $context['label']);
		$this->assertSame('Shown to visitors', $context['description']);
		$this->assertTrue($context['required']);
		$this->assertTrue($context['hidden']);
		$this->assertSame(['zxx' => 'Saved title'], $context['value']);
		$this->assertSame([], $context['errors']);
	}

	public function testRenderPassesFieldContextToResolvedTemplate(): void
	{
		$view = $this->view();
		$renderer = new FieldRenderer($view);
		$field = $this->field(TestText::class, 'headline');

		$html = $renderer->render($field, ['value' => 'Hello']);

		$this->assertSame('rendered:field/shell', $html);
		$this->assertSame('field/shell', $view->id);
		$this->assertSame($field, $view->context['field']);
		$this->assertSame('field/text', $view->context['fieldView']);
		$this->assertSame('headline', $view->context['properties']['name']);
		$this->assertSame(TestText::class, $view->context['properties']['type']);
		$this->assertSame('Hello', $view->context['value']);
	}

	public function testCodeViewRendersHiddenTextareaAndComponent(): void
	{
		$node = new class {
			#[Syntax('php', 'javascript')]
			public Code $snippet;
		};
		$hydrator = new FieldHydrator(Registry::withDefaults());
		$hydrator->hydrate(
			$node,
			[
				'snippet' => [
					'value' => ['zxx' => '<?php echo "x";'],
					'meta' => ['syntax' => ['zxx' => 'php']],
				],
			],
			$this->owner(),
		);
		$field = $hydrator->getField($node, 'snippet');
		$renderer = new FieldRenderer(new BoilerRenderer(self::root() . '/panel/views'));

		$html = $renderer->render($field, ['panelPath' => '/cp']);

		$this->assertStringContainsString('name="content[snippet][type]"', $html);
		$this->assertStringContainsString('name="content[snippet][value][zxx]"', $html);
		$this->assertStringContainsString('hidden', $html);
		$this->assertStringContainsString('data-module="/cp/assets/app/components/code.js"', $html);
		$this->assertStringContainsString('data-value-input="field-snippet"', $html);
		$this->assertStringContainsString('syntax="php"', $html);
		$this->assertStringContainsString('&lt;?php echo &quot;x&quot;;', $html);
	}

	public function testRichTextViewRendersHiddenTextareaAndComponent(): void
	{
		$node = new class {
			public RichText $body;
		};
		$hydrator = new FieldHydrator(Registry::withDefaults());
		$hydrator->hydrate(
			$node,
			[
				'body' => [
					'value' => ['zxx' => '<p>Hello <strong>world</strong></p>'],
				],
			],
			$this->owner(),
		);
		$field = $hydrator->getField($node, 'body');
		$renderer = new FieldRenderer(new BoilerRenderer(self::root() . '/panel/views'));

		$html = $renderer->render($field, ['panelPath' => '/cp']);

		$this->assertStringContainsString('name="content[body][type]"', $html);
		$this->assertStringContainsString('name="content[body][value][zxx]"', $html);
		$this->assertStringContainsString('hidden', $html);
		$this->assertStringContainsString('data-module="/cp/assets/app/components/richtext.js"', $html);
		$this->assertStringContainsString('data-value-input="field-body"', $html);
		$this->assertStringContainsString(
			'&lt;p&gt;Hello &lt;strong&gt;world&lt;/strong&gt;&lt;/p&gt;',
			$html,
		);
	}

	public function testJsonFieldViewsRenderStorageShape(): void
	{
		$node = new class {
			public Image $gallery;
			public Blocks $blocks;
		};
		$hydrator = new FieldHydrator(Registry::withDefaults());
		$hydrator->hydrate(
			$node,
			[
				'gallery' => [
					'value' => [
						'zxx' => [
							['file' => 'hero.jpg', 'meta' => ['alt' => ['en' => 'Hero']]],
						],
					],
				],
				'blocks' => [
					'value' => [
						'zxx' => [
							['type' => 'text', 'fields' => []],
						],
					],
				],
			],
			$this->owner(),
		);
		$renderer = new FieldRenderer(new BoilerRenderer(self::root() . '/panel/views'));
		$html = implode('', array_map(
			$renderer->render(...),
			$hydrator->getFields($node, ['gallery', 'blocks']),
		));

		$this->assertStringContainsString('name="content[gallery][value][zxx]"', $html);
		$this->assertStringContainsString('&quot;file&quot;: &quot;hero.jpg&quot;', $html);
		$this->assertStringContainsString('name="content[blocks][value][zxx]"', $html);
		$this->assertStringContainsString('&quot;type&quot;: &quot;text&quot;', $html);
	}

	public function testNativeFieldViewsRenderCanonicalInputs(): void
	{
		$node = new class {
			public Textarea $summary;
			public Number $score;
			public Checkbox $featured;
			#[Options(['news', ['value' => 'blog', 'label' => 'Blog']])]
			public Option $category;
			public Date $publishDate;
			public Time $publishTime;
			public Iframe $embed;
		};
		$hydrator = new FieldHydrator(Registry::withDefaults());
		$hydrator->hydrate(
			$node,
			[
				'summary' => ['value' => ['zxx' => 'Summary']],
				'score' => ['value' => ['zxx' => 13]],
				'featured' => ['value' => ['zxx' => true]],
				'category' => ['value' => ['zxx' => 'blog']],
				'publishDate' => ['value' => ['zxx' => '2026-06-19']],
				'publishTime' => ['value' => ['zxx' => '10:30']],
				'embed' => ['value' => ['zxx' => 'https://example.com/embed']],
			],
			$this->owner(),
		);
		$renderer = new FieldRenderer(new BoilerRenderer(self::root() . '/panel/views'));

		$html = implode('', array_map(
			$renderer->render(...),
			$hydrator->getFields($node, [
				'summary',
				'score',
				'featured',
				'category',
				'publishDate',
				'publishTime',
				'embed',
			]),
		));

		$this->assertStringContainsString('name="content[summary][value][zxx]"', $html);
		$this->assertStringContainsString('<textarea', $html);
		$this->assertStringContainsString('type="number"', $html);
		$this->assertStringContainsString('name="content[score][value][zxx]"', $html);
		$this->assertStringContainsString('type="checkbox"', $html);
		$this->assertStringContainsString('name="content[featured][value][zxx]"', $html);
		$this->assertStringContainsString('value="blog" selected', $html);
		$this->assertStringContainsString('type="date"', $html);
		$this->assertStringContainsString('type="time"', $html);
		$this->assertStringContainsString('type="url"', $html);
	}

	public function testTextViewRendersEscapedNativeInput(): void
	{
		$node = new class {
			#[Label('Title <b>')]
			public TestText $title;
		};
		$hydrator = new FieldHydrator(Registry::withDefaults());
		$hydrator->hydrate(
			$node,
			[
				'title' => [
					'value' => ['zxx' => '"><script>alert(1)</script>'],
				],
			],
			$this->owner(),
		);
		$field = $hydrator->getField($node, 'title');
		$renderer = new FieldRenderer(new BoilerRenderer(self::root() . '/panel/views'));

		$html = $renderer->render($field);

		$this->assertStringContainsString('name="content[title][type]"', $html);
		$this->assertStringContainsString('name="content[title][value][zxx]"', $html);
		$this->assertStringContainsString('Title &lt;b&gt;', $html);
		$this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
		$this->assertStringNotContainsString('Title <b>', $html);
		$this->assertStringNotContainsString('"><script>alert(1)</script>', $html);
	}

	/** @param class-string<Field> $class */
	private function field(string $class, string $name): Field
	{
		return new $class($name, $this->owner(), new ValueContext($name, []));
	}

	private function unknownField(): Field
	{
		return new class('unknown', $this->owner(), new ValueContext('unknown', [])) extends Field {
			#[Override]
			public function value(): Value
			{
				throw new RuntimeException('Not implemented for tests.');
			}

			#[Override]
			public function structure(mixed $value = null): array
			{
				return [
					'type' => self::class,
					'value' => $value,
				];
			}

			#[Override]
			public function shape(): Shape
			{
				return Shapes::create();
			}
		};
	}

	private function owner(): Owner
	{
		$config = $this->config();
		$request = $this->request();
		$locales = $request->get('locales');
		assert($locales instanceof Locales, 'The test request must provide locales.');
		$locale = $request->get('locale');
		assert($locale instanceof Locale, 'The test request must provide a locale.');

		return new class($config, $request, $locales, $locale) implements Owner {
			public function __construct(
				private readonly Config $config,
				private readonly Request $request,
				private readonly Locales $locales,
				private readonly Locale $locale,
			) {}

			#[Override]
			public function uid(): string
			{
				return 'test-node';
			}

			#[Override]
			public function locale(): Locale
			{
				return $this->locale;
			}

			#[Override]
			public function defaultLocale(): Locale
			{
				return $this->locales->getDefault();
			}

			#[Override]
			public function locales(): Locales
			{
				return $this->locales;
			}

			#[Override]
			public function request(): Request
			{
				return $this->request;
			}

			#[Override]
			public function config(): Config
			{
				return $this->config;
			}
		};
	}

	private function view(): Renderer
	{
		return new class implements Renderer {
			public string $id = '';

			/** @var array<string, mixed> */
			public array $context = [];

			#[Override]
			public function render(string $id, array $context): string
			{
				$this->id = $id;
				$this->context = $context;

				return 'rendered:' . $id;
			}

			#[Override]
			public function contentType(): string
			{
				return 'text/html';
			}
		};
	}
}

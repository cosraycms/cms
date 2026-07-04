<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Context;
use Cosray\Node\FieldOwner;
use Cosray\Schema\TranslateMode;
use Cosray\Tests\Fixtures\Field\TestCheckbox;
use Cosray\Tests\Fixtures\Field\TestCode;
use Cosray\Tests\Fixtures\Field\TestNumber;
use Cosray\Tests\Fixtures\Field\TestRichText;
use Cosray\Tests\Fixtures\Field\TestText;
use Cosray\Tests\TestCase;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class PrimitiveValueTest extends TestCase
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

		$request = new \Celemas\Core\Request($psrRequest);

		return new Context(
			$this->db(),
			$request,
			$this->config(['path.prefix' => '/cms']),
			$this->container(),
			$this->factory(),
		);
	}

	private function createOwner(Context $context): FieldOwner
	{
		return new FieldOwner($context, 'test-node');
	}

	private function seedAsset(
		Context $context,
		string $uid,
		string $filename,
		string $kind = 'image',
		array $meta = [],
	): void {
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		$context->assets()->add(new Asset(
			uid: $uid,
			disk: 'local',
			key: substr($uid, 0, 2) . "/{$uid}.{$ext}",
			filename: $filename,
			kind: $kind,
			meta: $meta,
			prefix: '/cms',
		));
	}

	public function testTextValueFallsBackToDefaultLocale(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestText('title', $owner, new ValueContext('title', [
			'value' => ['en' => 'Hello', 'de' => null],
		]));
		$field->translate();

		$context->request->set('locale', $context->locales()->get('de'));
		$value = $field->value();

		$this->assertSame('Hello', $value->unwrap());
		$this->assertSame('Hello', (string) $value);
		$this->assertTrue($value->isset());
	}

	public function testTextValueReturnsEmptyWhenMissing(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestText('title', $owner, new ValueContext('title', [
			'value' => ['en' => null, 'de' => null],
		]));
		$field->translate();

		$context->request->set('locale', $context->locales()->get('de'));
		$value = $field->value();

		$this->assertSame('', $value->unwrap());
		$this->assertSame('', (string) $value);
		$this->assertFalse($value->isset());
	}

	public function testValueBaseExposesCustomAttributes(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestText('title', $owner, new ValueContext('title', [
			'value' => ['en' => 'Hello'],
			'class' => 'hero',
			'id' => 'section',
			'custom' => 'custom-value',
		]));

		$value = $field->value();

		$this->assertSame('hero', $value->styleClass());
		$this->assertSame('section', $value->elementId());
		$this->assertSame('custom-value', $value->custom);

		$this->throws(
			\Cosray\Exception\NoSuchProperty::class,
			"The field 'title' doesn't have the property 'missing'",
		);
		$value->missing;
	}

	public function testRichTextValueUsesExcerptAndSanitizedOutput(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestRichText('body', $owner, new ValueContext('body', [
			'value' => ['en' => '<p>Hello <strong>World</strong></p>'],
		]));
		$field->translate();

		$value = $field->value();
		$this->assertSame('Hello World', $value->excerpt(2));
		$this->assertStringContainsString('Hello', $value->clean());
	}

	public function testCodeValueFallsBackToDefaultLocaleAndKeepsSyntax(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestCode('snippet', $owner, new ValueContext('snippet', [
			'value' => ['en' => '<?php echo 1;', 'de' => null],
			'syntax' => 'php',
		]));
		$field->translate();
		$field->syntaxes(['php', 'javascript']);

		$context->request->set('locale', $context->locales()->get('de'));
		$value = $field->value();

		$this->assertSame('<?php echo 1;', $value->unwrap());
		$this->assertSame('php', $value->syntax());
		$this->assertSame('&lt;?php echo 1;', (string) $value);
		$this->assertTrue($value->isset());
	}

	public function testCodeValueUsesConfiguredDefaultSyntax(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestCode('snippet', $owner, new ValueContext('snippet', [
			'value' => 'const answer = 42;',
		]));
		$field->syntaxes(['javascript', 'php']);

		$value = $field->value();

		$this->assertSame('const answer = 42;', $value->unwrap());
		$this->assertSame('javascript', $value->syntax());
	}

	public function testCodeStructureContainsSharedSyntaxAndLocaleValues(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestCode('snippet', $owner, new ValueContext('snippet', []));
		$field->translate();
		$field->syntaxes(['php', 'javascript']);

		$structure = $field->structure();

		$this->assertSame($field::class, $structure['type']);
		$this->assertSame('php', $structure['meta']['syntax'][\Cosray\Field\Field::NEUTRAL_LOCALE]);
		$this->assertIsArray($structure['value']);
		$this->assertArrayHasKey('en', $structure['value']);
		$this->assertArrayHasKey('de', $structure['value']);
	}

	public function testCodeShapeRejectsUnsupportedSyntax(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestCode('snippet', $owner, new ValueContext('snippet', []));
		$field->syntaxes(['php', 'javascript']);

		$shape = $field->shape();
		$valid = $shape->validate([
			'type' => $field::class,
			'meta' => ['syntax' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 'php']],
			'value' => [\Cosray\Field\Field::NEUTRAL_LOCALE => '<?php echo 1;'],
		]);
		$invalid = $shape->validate([
			'type' => $field::class,
			'meta' => ['syntax' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 'ruby']],
			'value' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 'puts 1'],
		]);

		$this->assertTrue($valid->valid());
		$this->assertFalse($invalid->valid());
	}

	public function testNumberValueCastsNumeric(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestNumber('count', $owner, new ValueContext('count', [
			'value' => '42',
		]));

		$value = $field->value();
		$this->assertSame(42, $value->unwrap());
		$this->assertTrue($value->isset());
		$this->assertSame('42', (string) $value);
	}

	public function testNumberValueIsNullWhenInvalid(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestNumber('count', $owner, new ValueContext('count', [
			'value' => 'not-a-number',
		]));

		$value = $field->value();
		$this->assertNull($value->unwrap());
		$this->assertFalse($value->isset());
		$this->assertSame('', (string) $value);
	}

	public function testDecimalValueFormatsAndLocalizes(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$valueContext = new ValueContext('price', ['value' => '12.5']);
		$field = new TestNumber('price', $owner, $valueContext);
		$value = new \Cosray\Value\Decimal($owner, $field, $valueContext);
		$this->assertSame(12.5, $value->unwrap());
		$this->assertTrue($value->isset());
		$this->assertSame('12.5', (string) $value->unwrap());
		$this->assertSame('12.50', $value->localize(2, 'en'));
		$this->assertStringContainsString('12.50', $value->currency('USD', 2, 'en'));
	}

	public function testCheckboxValueDefaultsFalse(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestCheckbox('flag', $owner, new ValueContext('flag', [
			'value' => null,
		]));

		$value = $field->value();
		$this->assertFalse($value->unwrap());
		$this->assertSame('', (string) $value);
		$this->assertTrue($value->isset());
	}

	public function testFilesValueIteratesAndCounts(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$valueContext = new ValueContext('attachments', [
			'files' => [
				['uid' => 'qonepdf123456'],
				['uid' => 'qtwopdf123456'],
			],
		]);
		$field = new \Cosray\Field\File('attachments', $owner, $valueContext);
		$value = $field->value();

		$this->assertInstanceOf(\Cosray\Value\Files::class, $value);
		$this->assertSame(2, $value->count());
		$this->assertTrue($value->isset());
		$this->assertSame('Files: count(2)', (string) $value);
		$this->assertInstanceOf(\Cosray\Value\File::class, $value->first());

		$files = [];
		foreach ($value as $file) {
			$files[] = $file;
		}

		$this->assertCount(2, $files);
	}

	public function testTranslatedFileFallsBackToDefaultLocale(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\File('attachment', $owner, new ValueContext('attachment', [
			'files' => [
				'en' => [
					['uid' => 'qmanualpdf123', 'title' => 'Manual'],
				],
				'de' => [
					['uid' => null, 'title' => null],
				],
			],
		]));
		$field->limit(1);
		$field->translate(TranslateMode::Asymmetric);
		$context->request->set('locale', $context->locales()->get('de'));

		$value = $field->value();

		$this->assertTrue($value->isset());
		$this->assertSame('Manual', $value->title());
	}

	public function testTranslatedFileIsEmptyWhenMissing(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\File('attachment', $owner, new ValueContext('attachment', [
			'files' => [
				'en' => [
					['uid' => null, 'title' => null],
				],
				'de' => [
					['uid' => null, 'title' => null],
				],
			],
		]));
		$field->limit(1);
		$field->translate(TranslateMode::Asymmetric);
		$context->request->set('locale', $context->locales()->get('de'));

		$value = $field->value();

		$this->assertFalse($value->isset());
		$this->assertSame('', $value->title());
	}

	public function testTranslatedFilesReturnsTranslatedFileInstances(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\File('attachments', $owner, new ValueContext('attachments', [
			'files' => [
				'en' => [
					['uid' => 'qspecpdf12345', 'title' => 'Spec'],
				],
				'de' => [
					['uid' => null, 'title' => null],
				],
			],
		]));
		$field->translate(TranslateMode::Asymmetric);
		$context->request->set('locale', $context->locales()->get('de'));

		$value = $field->value();

		$this->assertInstanceOf(\Cosray\Value\TranslatedFiles::class, $value);
		$this->assertInstanceOf(\Cosray\Value\TranslatedFile::class, $value->current());
		$this->assertSame('Spec', $value->current()->title());
	}

	public function testImageValueBuildsMediaPaths(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$this->seedAsset($context, 'heroimg123456', 'hero.jpg');
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', [
			'files' => [
				['uid' => 'heroimg123456', 'alt' => ['en' => 'Hero']],
			],
		]));
		$field->limit(1);

		$value = $field->value();
		$this->assertInstanceOf(\Cosray\Value\Image::class, $value);

		$this->assertStringContainsString(
			'/cms/media/image/heroimg123456/hero.jpg',
			$value->publicPath(),
		);
		$this->assertStringContainsString('http://www.example.com', $value->url());
		$this->assertSame('Hero', $value->alt());
	}

	public function testImageTagUsesMediaUrlAndAlt(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$this->seedAsset($context, 'heroimg123456', 'hero.jpg');
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', [
			'files' => [
				[
					'uid' => 'heroimg123456',
					'alt' => ['en' => 'Hero'],
					'title' => ['en' => 'Hero Title'],
				],
			],
		]));
		$field->limit(1);

		$value = $field->value();
		$this->assertInstanceOf(\Cosray\Value\Image::class, $value);
		$tag = $value->tag(true, 'hero-image');

		$this->assertStringContainsString('class="hero-image"', $tag);
		$this->assertStringContainsString(
			'src="http://www.example.com/cms/media/image/heroimg123456/hero.jpg"',
			$tag,
		);
		$this->assertStringContainsString('alt="Hero"', $tag);
		$this->assertStringContainsString(
			'data-path-original="/cms/media/image/heroimg123456/hero.jpg"',
			$tag,
		);
	}

	public function testTranslatedImageFallsBackToDefaultLocale(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$this->seedAsset($context, 'heroimg123456', 'hero.jpg');
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', [
			'files' => [
				'en' => [
					['uid' => 'heroimg123456', 'alt' => 'Hero'],
				],
				'de' => [
					['uid' => null, 'alt' => null],
				],
			],
		]));
		$field->limit(1);
		$field->translate(TranslateMode::Asymmetric);
		$context->request->set('locale', $context->locales()->get('de'));

		$value = $field->value();
		$this->assertInstanceOf(\Cosray\Value\TranslatedImage::class, $value);

		$this->assertTrue($value->isset());
		$this->assertSame('Hero', $value->alt());
		$this->assertStringContainsString(
			'/cms/media/image/heroimg123456/hero.jpg',
			$value->publicPath(),
		);
	}

	public function testFileValueTitleFallsBackToDefaultLocale(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$this->seedAsset($context, 'qmanualpdf123', 'manual.pdf', kind: 'file');
		$field = new \Cosray\Field\File('document', $owner, new ValueContext('document', [
			'files' => [
				[
					'uid' => 'qmanualpdf123',
					'title' => [
						'en' => 'Manual',
						'de' => null,
					],
				],
			],
		]));
		$field->limit(1);
		$field->translate();
		$context->request->set('locale', $context->locales()->get('de'));

		$value = $field->value();

		$this->assertSame('manual.pdf', $value->filename());
		$this->assertSame('Manual', $value->title());
	}

	public function testImageValueUsesTranslatedAltAndTitle(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', [
			'files' => [
				[
					'uid' => 'heroimg123456',
					'alt' => [
						'en' => 'Hero',
						'de' => null,
					],
					'title' => [
						'en' => 'Hero Image',
						'de' => null,
					],
				],
			],
		]));
		$field->limit(1);
		$field->translate();
		$context->request->set('locale', $context->locales()->get('de'));

		$value = $field->value();

		$this->assertSame('Hero', $value->alt());
		$this->assertSame('Hero Image', $value->title());
	}

	public function testMetaFallsBackToCatalogDefaults(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$this->seedAsset($context, 'heroimg123456', 'hero.jpg', meta: [
			'alt' => ['en' => 'Catalog Alt'],
			'title' => ['en' => 'Catalog Title'],
		]);
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', [
			'type' => \Cosray\Field\Image::class,
			'value' => [
				\Cosray\Field\Field::NEUTRAL_LOCALE => [
					// The upload UI creates empty locale maps; empty per-use
					// meta must not shadow the catalog default.
					['uid' => 'heroimg123456', 'meta' => ['alt' => ['en' => '']]],
				],
			],
		]));
		$field->limit(1);

		$value = $field->value();

		$this->assertSame('Catalog Alt', $value->alt());
		$this->assertSame('Catalog Title', $value->title());
	}

	public function testNonEmptyMetaOverridesCatalogDefaults(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$this->seedAsset($context, 'heroimg123456', 'hero.jpg', meta: [
			'alt' => ['en' => 'Catalog Alt'],
		]);
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', [
			'type' => \Cosray\Field\Image::class,
			'value' => [
				\Cosray\Field\Field::NEUTRAL_LOCALE => [
					['uid' => 'heroimg123456', 'meta' => ['alt' => ['en' => 'Node Alt']]],
				],
			],
		]));
		$field->limit(1);

		$value = $field->value();

		$this->assertSame('Node Alt', $value->alt());
	}

	public function testTranslatedImageFallsBackToDefaultLocaleWithTitle(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', [
			'files' => [
				'en' => [
					[
						'uid' => 'heroimg123456',
						'alt' => 'Hero',
						'title' => 'Hero Image',
						'link' => '/hero',
					],
				],
				'de' => [
					[
						'uid' => null,
						'alt' => null,
						'title' => null,
						'link' => null,
					],
				],
			],
		]));
		$field->limit(1);
		$field->translate(TranslateMode::Asymmetric);
		$context->request->set('locale', $context->locales()->get('de'));

		$value = $field->value();

		$this->assertSame('Hero', $value->alt());
		$this->assertSame('Hero Image', $value->title());
	}

	public function testVideoValueRendersSourceTag(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$valueContext = new ValueContext('clip', [
			'files' => [
				['uid' => 'vclipmp412345', 'title' => 'Clip'],
			],
		]);
		$field = new \Cosray\Field\Video('clip', $owner, $valueContext);

		$value = new class($owner, $field, $valueContext) extends \Cosray\Value\Video {
			public function url(bool $bust = false): string
			{
				return 'http://www.example.com/assets/clip.mp4';
			}

			public function mimeType(): string
			{
				return 'video/mp4';
			}
		};

		$this->assertSame(
			'<video controls><source src="http://www.example.com/assets/clip.mp4" type="video/mp4"/></video>',
			(string) $value,
		);
	}

	public function testTranslatedImagesReturnsTranslatedImageItems(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Image('gallery', $owner, new ValueContext('gallery', [
			'files' => [
				'en' => [
					['uid' => 'heroimg123456', 'alt' => 'Hero'],
				],
				'de' => [
					['uid' => null, 'alt' => null],
				],
			],
		]));
		$field->translate(TranslateMode::Asymmetric);
		$context->request->set('locale', $context->locales()->get('de'));

		$value = $field->value();

		$this->assertInstanceOf(\Cosray\Value\TranslatedImages::class, $value);
		$this->assertInstanceOf(\Cosray\Value\TranslatedImage::class, $value->current());
		$this->assertSame('Hero', $value->current()->alt());
	}

	public function testImageValueResizeAddsQueryString(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', [
			'files' => [
				['uid' => 'heroimg123456', 'alt' => ['en' => 'Hero']],
			],
		]));
		$field->limit(1);

		$value = $field->value();
		$this->assertInstanceOf(\Cosray\Value\Image::class, $value);
		$value = $value->width(320, true)->quality(80);

		$this->assertStringContainsString('resize=width', $value->publicPath());
		$this->assertStringContainsString('w=320', $value->publicPath());
		$this->assertStringContainsString('enlarge=true', $value->publicPath());
		$this->assertStringContainsString('quality=80', $value->publicPath());
	}

	public function testImagesValueIteratesOverImages(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Image('gallery', $owner, new ValueContext('gallery', [
			'files' => [
				['uid' => 'oneimg1234567', 'alt' => ['en' => 'One']],
				['uid' => 'twoimg1234567', 'alt' => ['en' => 'Two']],
			],
		]));

		$value = $field->value();
		$this->assertInstanceOf(\Cosray\Value\Images::class, $value);

		$this->assertSame(2, $value->count());
		$this->assertInstanceOf(\Cosray\Value\Image::class, $value->first());
		$this->assertInstanceOf(\Cosray\Value\Image::class, $value->current());
		$this->assertInstanceOf(\Cosray\Value\Image::class, $value->get(1));
		$this->assertSame('One', $value->first()->alt());
		$this->assertSame('Two', $value->get(1)->alt());
	}

	public function testFileShapeRejectsMoreItemsThanLimitMax(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\File('downloads', $owner, new ValueContext('downloads', []));
		$field->limit(2);

		$shape = $field->shape();

		$valid = $shape->validate([
			'type' => $field::class,
			'value' => [
				\Cosray\Field\Field::NEUTRAL_LOCALE => [
					['uid' => 'qdocafile1234'],
					['uid' => 'qdocbfile1234'],
				],
			],
		]);
		$invalid = $shape->validate([
			'type' => $field::class,
			'value' => [
				\Cosray\Field\Field::NEUTRAL_LOCALE => [
					['uid' => 'qdocafile1234'],
					['uid' => 'qdocbfile1234'],
					['uid' => 'qdoccfile1234'],
				],
			],
		]);

		$this->assertTrue($valid->valid());
		$this->assertFalse($invalid->valid());
	}

	public function testFileShapeRejectsFewerItemsThanLimitMin(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\File('downloads', $owner, new ValueContext('downloads', []));
		$field->limit(3, 2);

		$shape = $field->shape();

		$valid = $shape->validate([
			'type' => $field::class,
			'value' => [
				\Cosray\Field\Field::NEUTRAL_LOCALE => [
					['uid' => 'qdocafile1234'],
					['uid' => 'qdocbfile1234'],
				],
			],
		]);
		$invalid = $shape->validate([
			'type' => $field::class,
			'value' => [
				\Cosray\Field\Field::NEUTRAL_LOCALE => [
					['uid' => 'qdocafile1234'],
				],
			],
		]);

		$this->assertTrue($valid->valid());
		$this->assertFalse($invalid->valid());
	}

	public function testTranslatedFileShapeAppliesLimitPerLocale(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\File('downloads', $owner, new ValueContext('downloads', []));
		$field->translate(TranslateMode::Asymmetric);
		$field->limit(1);

		$shape = $field->shape();

		$valid = $shape->validate([
			'type' => $field::class,
			'value' => [
				'en' => [
					['uid' => 'qdocafile1234'],
				],
				'de' => [
					['uid' => 'qdocbfile1234'],
				],
			],
		]);
		$invalid = $shape->validate([
			'type' => $field::class,
			'value' => [
				'en' => [
					['uid' => 'qdocafile1234'],
					['uid' => 'qdocbfile1234'],
				],
				'de' => [
					['uid' => 'qdoccfile1234'],
				],
			],
		]);

		$this->assertTrue($valid->valid());
		$this->assertFalse($invalid->valid());
	}

	public function testRequiredAsymmetricImageRequiresDefaultLocaleOnly(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Image('hero', $owner, new ValueContext('hero', []));
		$field->required();
		$field->translate(TranslateMode::Asymmetric);

		$shape = $field->shape();

		$valid = $shape->validate([
			'type' => $field::class,
			'value' => [
				'en' => [
					['uid' => 'heroimg123456'],
				],
			],
		]);
		$invalid = $shape->validate([
			'type' => $field::class,
			'value' => [
				'de' => [
					['uid' => 'heldimg123456'],
				],
			],
		]);

		$this->assertTrue($valid->valid());
		$this->assertFalse($invalid->valid());
	}

	public function testRequiredAsymmetricBlocksRequiresDefaultLocaleOnly(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Blocks('content', $owner, new ValueContext('content', []));
		$field->required();
		$field->translate(TranslateMode::Asymmetric);

		$shape = $field->shape();
		$block = [
			'type' => 'text',
			'rowspan' => 1,
			'colspan' => 12,
			'value' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 'Hello'],
		];

		$valid = $shape->validate([
			'type' => $field::class,
			'meta' => ['columns' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 12]],
			'value' => [
				'en' => [$block],
			],
		]);
		$invalid = $shape->validate([
			'type' => $field::class,
			'meta' => ['columns' => [\Cosray\Field\Field::NEUTRAL_LOCALE => 12]],
			'value' => [
				'de' => [$block],
			],
		]);

		$this->assertTrue($valid->valid());
		$this->assertFalse($invalid->valid());
	}

	public function testOptionValueUsesProvidedValue(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Option('status', $owner, new ValueContext('status', [
			'value' => 'draft',
		]));

		$value = $field->value();
		$this->assertSame('draft', $value->unwrap());
		$this->assertSame(['value' => 'draft'], $value->json());
		$this->assertTrue($value->isset());
	}

	public function testRadioValueUsesStringValue(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Radio('choice', $owner, new ValueContext('choice', [
			'value' => 'yes',
		]));

		$value = $field->value();
		$this->assertSame('yes', $value->unwrap());
		$this->assertSame('yes', $value->json());
		$this->assertTrue($value->isset());
	}

	public function testStrValueEscapesHtml(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Radio('choice', $owner, new ValueContext('choice', [
			'value' => '<strong>Yes</strong>',
		]));

		$value = $field->value();

		$this->assertSame('<strong>Yes</strong>', $value->unwrap());
		$this->assertSame('<strong>Yes</strong>', $value->json());
		$this->assertSame('&lt;strong&gt;Yes&lt;/strong&gt;', (string) $value);
		$this->assertTrue($value->isset());
	}

	public function testStrValueIsEmptyWhenMissing(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Radio('choice', $owner, new ValueContext('choice', [
			'value' => '',
		]));

		$value = $field->value();

		$this->assertSame('', $value->unwrap());
		$this->assertSame('', $value->json());
		$this->assertSame('', (string) $value);
		$this->assertFalse($value->isset());
	}

	public function testDateTimeValueFormatsToExpectedString(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\DateTime('timestamp', $owner, new ValueContext('timestamp', [
			'value' => '2025-01-31 13:45:10',
			'timezone' => 'UTC',
		]));

		$value = $field->value();
		$this->assertSame('2025-01-31 13:45:10', $value->format(\Cosray\Value\DateTime::FORMAT));
		$this->assertSame('2025-01-31 13:45:10', (string) $value);
		$this->assertTrue($value->isset());
	}

	public function testDateValueFormatsToExpectedString(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Date('date', $owner, new ValueContext('date', [
			'value' => '2025-01-31',
		]));

		$value = $field->value();
		$this->assertSame('2025-01-31', $value->format(\Cosray\Value\Date::FORMAT));
		$this->assertSame('2025-01-31', (string) $value);
		$this->assertTrue($value->isset());
	}

	public function testTimeValueFormatsToExpectedString(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Time('time', $owner, new ValueContext('time', [
			'value' => '13:45',
		]));

		$value = $field->value();
		$this->assertSame('13:45', $value->format(\Cosray\Value\Time::FORMAT));
		$this->assertSame('13:45', (string) $value);
		$this->assertTrue($value->isset());
	}

	public function testIframeValueFallsBackToDefaultLocale(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Iframe('embed', $owner, new ValueContext('embed', []));
		$field->translate();

		$context->request->set('locale', $context->locales()->get('de'));
		$value = new \Cosray\Value\Iframe($owner, $field, new ValueContext('embed', [
			'value' => ['en' => '<iframe></iframe>', 'de' => null],
		]));

		$this->assertSame('<iframe></iframe>', $value->unwrap());
		$this->assertSame('&lt;iframe&gt;&lt;/iframe&gt;', (string) $value);
		$this->assertTrue($value->isset());
	}

	public function testIframeValueIsEmptyWhenMissing(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Iframe('embed', $owner, new ValueContext('embed', []));
		$field->translate();

		$context->request->set('locale', $context->locales()->get('de'));
		$value = new \Cosray\Value\Iframe($owner, $field, new ValueContext('embed', [
			'value' => ['en' => null, 'de' => null],
		]));

		$this->assertSame('', $value->unwrap());
		$this->assertSame('', (string) $value);
		$this->assertFalse($value->isset());
	}

	public function testYoutubeValueUsesAspectRatio(): void
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new \Cosray\Field\Youtube('video', $owner, new ValueContext('video', [
			'value' => 'abc123',
			'id' => 'abc123',
			'aspectRatioX' => 16,
			'aspectRatioY' => 9,
		]));

		$value = $field->value();
		$this->assertSame('abc123', $value->unwrap());
		$this->assertSame('abc123', $value->json());
		$this->assertTrue($value->isset());
		$this->assertStringContainsString('padding-top: 56.25%', (string) $value);
	}
}

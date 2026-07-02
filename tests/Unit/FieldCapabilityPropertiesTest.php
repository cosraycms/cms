<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\Request;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Code;
use Cosray\Field\FieldHydrator;
use Cosray\Field\Image;
use Cosray\Field\Option;
use Cosray\Field\Owner;
use Cosray\Field\Schema\Registry;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\Types;
use Cosray\Schema\Columns;
use Cosray\Schema\Description;
use Cosray\Schema\Hidden;
use Cosray\Schema\Immutable;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Options;
use Cosray\Schema\Required;
use Cosray\Schema\Rows;
use Cosray\Schema\Syntax;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;
use Cosray\Schema\Validate;
use Cosray\Schema\Width;
use Cosray\Tests\Fixtures\Node\NodeWithFieldIconAttribute;
use Cosray\Tests\TestCase;
use Cosray\Value\ValueContext;

final class FieldCapabilityPropertiesTest extends TestCase
{
	private Registry $registry;

	protected function setUp(): void
	{
		parent::setUp();
		$this->registry = Registry::withDefaults();
	}

	private function createOwner(): Owner
	{
		$config = $this->config();
		$request = $this->request();
		$locales = new Locales();
		$locales->add('en', 'English');
		$locale = $locales->getDefault();

		return new class($config, $request, $locales, $locale) implements Owner {
			public function __construct(
				private readonly Config $config,
				private readonly Request $request,
				private readonly Locales $locales,
				private readonly Locale $locale,
			) {}

			public function uid(): string
			{
				return 'test-node';
			}

			public function locale(): Locale
			{
				return $this->locale;
			}

			public function defaultLocale(): Locale
			{
				return $this->locale;
			}

			public function locales(): Locales
			{
				return $this->locales;
			}

			public function request(): Request
			{
				return $this->request;
			}

			public function config(): Config
			{
				return $this->config;
			}
		};
	}

	private function createTextField(string $name = 'test'): Text
	{
		return new Text($name, $this->createOwner(), new ValueContext($name, []));
	}

	private function createImageField(string $name = 'image'): Image
	{
		return new Image($name, $this->createOwner(), new ValueContext($name, []));
	}

	private function createBlocksField(string $name = 'blocks'): Blocks
	{
		return new Blocks($name, $this->createOwner(), new ValueContext($name, []));
	}

	private function createOptionField(string $name = 'option'): Option
	{
		return new Option($name, $this->createOwner(), new ValueContext($name, []));
	}

	private function createCodeField(string $name = 'code'): Code
	{
		return new Code($name, $this->createOwner(), new ValueContext($name, []));
	}

	private function applyAndGetProperties(object $meta, $field): array
	{
		$handler = $this->registry->getHandler($meta);
		$handler->apply($meta, $field);

		return $handler->properties($meta, $field);
	}

	public function testLabelCapabilityReturnsLabelProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Label('Test Label');

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('label', $properties);
		$this->assertEquals('Test Label', $properties['label']);
	}

	public function testFieldIconAttributeIsExposedViaFieldProperties(): void
	{
		$owner = $this->createOwner();
		$node = new NodeWithFieldIconAttribute();
		$hydrator = new FieldHydrator(new Services($this->registry, new Types()));
		$fieldNames = $hydrator->hydrate($node, [], $owner);
		$field = $hydrator->getField($node, 'title');

		$this->assertSame(['title'], $fieldNames);
		$this->assertSame(
			[
				'id' => 'bi:type',
				'args' => [
					'color' => '#00ff00',
					'class' => 'cms-field-icon',
					'style' => 'width: 1rem',
				],
			],
			$field->properties()['icon'],
		);
	}

	public function testDescriptionCapabilityReturnsDescriptionProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Description('Test description');

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('description', $properties);
		$this->assertEquals('Test description', $properties['description']);
	}

	public function testHiddenCapabilityReturnsHiddenProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Hidden();

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('hidden', $properties);
		$this->assertTrue($properties['hidden']);
	}

	public function testRequiredCapabilityReturnsRequiredProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Required();

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('required', $properties);
		$this->assertTrue($properties['required']);
	}

	public function testImmutableCapabilityReturnsImmutableProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Immutable();

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('immutable', $properties);
		$this->assertTrue($properties['immutable']);
	}

	public function testRowsCapabilityReturnsRowsProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Rows(10);

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('rows', $properties);
		$this->assertEquals(10, $properties['rows']);
	}

	public function testWidthCapabilityReturnsWidthProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Width(6);

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('width', $properties);
		$this->assertEquals(6, $properties['width']);
	}

	public function testColumnsCapabilityReturnsColumnsProperties(): void
	{
		$field = $this->createBlocksField();
		$meta = new Columns(12, 2);

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('columns', $properties);
		$this->assertArrayHasKey('minCellWidth', $properties);
		$this->assertEquals(12, $properties['columns']);
		$this->assertEquals(2, $properties['minCellWidth']);
	}

	public function testOptionsCapabilityReturnsOptionsProperty(): void
	{
		$field = $this->createOptionField();
		$options = ['option1', 'option2', 'option3'];
		$meta = new Options($options);

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('options', $properties);
		$this->assertEquals($options, $properties['options']);
	}

	public function testTranslateCapabilityReturnsTranslateProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Translate();

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('translate', $properties);
		$this->assertTrue($properties['translate']);
		$this->assertSame('symmetric', $properties['translateMode']);
		$this->assertSame(TranslateMode::Symmetric, $field->translateMode());
	}

	public function testAsymmetricTranslateCapabilitySetsAsymmetricMode(): void
	{
		$field = $this->createImageField();
		$meta = new Translate(TranslateMode::Asymmetric);

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('translate', $properties);
		$this->assertTrue($properties['translate']);
		$this->assertSame('asymmetric', $properties['translateMode']);
		$this->assertSame(TranslateMode::Asymmetric, $field->translateMode());
	}

	public function testUnsupportedTranslationModeThrows(): void
	{
		$field = $this->createTextField();
		$meta = new Translate(TranslateMode::Asymmetric);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('does not support asymmetric translation');

		$this->applyAndGetProperties($meta, $field);
	}

	public function testLimitCapabilityReturnsLimitProperty(): void
	{
		$field = new class('image', $this->createOwner(), new ValueContext('image', [])) extends
			Image implements \Cosray\Field\Capability\Limitable {
			use \Cosray\Field\Capability\IsLimitable;
		};
		$meta = new Limit(5, 2);

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('limit', $properties);
		$this->assertEquals(['min' => 2, 'max' => 5], $properties['limit']);
	}

	public function testImageFieldPropertiesDoNotExposeLimitWithoutSchema(): void
	{
		$field = $this->createImageField();

		$properties = $field->properties();

		$this->assertArrayNotHasKey('limit', $properties);
	}

	public function testValidateCapabilityReturnsValidatorsProperty(): void
	{
		$field = $this->createTextField();
		$meta = new Validate('minLength:5', 'maxLength:100');

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('validators', $properties);
		$this->assertContains('minLength:5', $properties['validators']);
		$this->assertContains('maxLength:100', $properties['validators']);
	}

	public function testSyntaxCapabilityReturnsSyntaxesProperty(): void
	{
		$field = new class('code', $this->createOwner(), new ValueContext('code', [])) extends
			Text implements \Cosray\Field\Capability\SyntaxAware {
			use \Cosray\Field\Capability\IsSyntaxAware;
		};
		$meta = new Syntax('php', 'javascript', 'php');

		$properties = $this->applyAndGetProperties($meta, $field);

		$this->assertArrayHasKey('syntaxes', $properties);
		$this->assertEquals(['php', 'javascript'], $properties['syntaxes']);
	}

	public function testCodeFieldPropertiesAlwaysExposeDefaultSyntaxes(): void
	{
		$field = $this->createCodeField();

		$properties = $field->properties();

		$this->assertArrayHasKey('syntaxes', $properties);
		$this->assertEquals(['plaintext'], $properties['syntaxes']);
	}
}

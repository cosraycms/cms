<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Checkbox;
use Cosray\Field\FieldHydrator;
use Cosray\Field\Owner;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\I18n\SchemaScanner;
use Cosray\Locales;
use Cosray\Panel\FormPatch;
use Cosray\Schema\Nullable;
use Cosray\Schema\StateLabels;
use Cosray\Tests\TestCase;
use Cosray\Value\ValueContext;
use PHPUnit\Framework\Attributes\DataProvider;

final class CheckboxTest extends TestCase
{
	public function testStateLabelsAreTranslatedWhenTheDescriptorIsEmitted(): void
	{
		$node = new class {
			#[StateLabels(true: 'field:yes', false: 'field:no')]
			public Checkbox $flag;
		};
		new FieldHydrator(Services::withDefaults())->hydrate($node, [], $this->createStub(Owner::class));

		Verba::activate(new Translator('de', new Locales()->catalogs()));

		try {
			$this->assertSame(
				['true' => 'Ja', 'false' => 'Nein'],
				$node->flag->properties()['control']['props']['labels'],
			);
		} finally {
			Verba::deactivate();
		}

		$this->assertSame('field:yes', $node->flag->stateLabels->true);
	}

	public function testStateLabelsAreIncludedInSchemaTranslationScanning(): void
	{
		$node = new class {
			#[StateLabels(true: 'Enabled', false: 'Disabled', null: 'Automatic')]
			public Checkbox $flag;
		};

		$messages = new SchemaScanner([$node::class])->scan();
		$this->assertSame(['Enabled', 'Disabled', 'Automatic'], array_column($messages, 'id'));
	}

	public function testNullableAttributeEnablesTheThreeStateControl(): void
	{
		$node = new class {
			#[Nullable]
			public Checkbox $flag;
		};
		new FieldHydrator(Services::withDefaults())->hydrate($node, [], $this->createStub(Owner::class));

		$this->assertTrue($node->flag->properties()['control']['props']['nullable']);
		$this->assertNull($node->flag->structure()['value']['zxx']);
	}

	public function testNullableRejectsUnrelatedFields(): void
	{
		$node = new class {
			#[Nullable]
			public Text $flag;
		};

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Nullable requires a Checkbox field.');
		new FieldHydrator(Services::withDefaults())->hydrate($node, [], $this->createStub(Owner::class));
	}

	public function testDefaultsDoNotReplaceExplicitNullOrFalse(): void
	{
		$binary = $this->field();
		$nullable = $this->field(nullable: true);
		$this->assertFalse($binary->structure()['value']['zxx']);
		$this->assertNull($nullable->structure()['value']['zxx']);
		$binary->default(true);
		$this->assertTrue($binary->structure(null)['value']['zxx']);

		$nullable->default(true);
		$this->assertTrue($nullable->structure()['value']['zxx']);
		$this->assertNull($nullable->structure(null)['value']['zxx']);
		$this->assertNull($nullable->structure(['zxx' => null])['value']['zxx']);
		$this->assertFalse($nullable->structure(false)['value']['zxx']);
	}

	#[DataProvider('states')]
	public function testNullableStatesSurviveSubmissionValidationAndValueAccess(
		?bool $state,
		string|bool|null $input,
	): void {
		$field = $this->field(nullable: true);
		$patch = new FormPatch([$field->properties()]);
		$content = $patch->content(
			['flag' => $field->structure(true)],
			['flag' => ['value' => ['zxx' => $input]]],
		);
		$result = $field->shape()->validate($content['flag']);
		$this->assertTrue($result->valid());
		$this->assertSame($state, $result->values()['value']['zxx']);

		$value = $this->field(nullable: true, data: $result->values())->value();
		$this->assertSame($state, $value->unwrap());
		$this->assertSame($state, $value->json());
		$this->assertSame($state !== null, $value->isset());
		$this->assertSame((string) $state, (string) $value);
	}

	public static function states(): array
	{
		return [
			'unset' => [null, ''],
			'yes' => [true, '1'],
			'no' => [false, '0'],
			'JSON null' => [null, null],
			'JSON true' => [true, true],
			'JSON false' => [false, false],
		];
	}

	public function testRequiredAcceptsFalseButRejectsNullAndMissingValues(): void
	{
		foreach ([false, true] as $nullable) {
			$field = $this->field(nullable: $nullable)->required();
			$this->assertTrue($field->shape()->validate($field->structure(false))->valid());
			$this->assertFalse(
				$field
					->shape()
					->validate(['type' => Checkbox::class, 'value' => ['zxx' => null]])
					->valid(),
			);
			$this->assertFalse($field->shape()->validate(['type' => Checkbox::class])->valid());
		}
	}

	public function testUnsubmittedAndImmutableNullableValuesArePreserved(): void
	{
		$field = $this->field(nullable: true);
		$stored = ['flag' => $field->structure(true)];
		$this->assertSame($stored, new FormPatch([$field->properties()])->content($stored, []));
		$this->assertSame($stored, new FormPatch([$field->properties()])->content($stored, [
			'flag' => ['value' => []],
		]));
		$this->assertSame($stored, new FormPatch([['immutable' => true] + $field->properties()])->content(
			$stored,
			['flag' => ['value' => ['zxx' => null]]],
		));
	}

	public function testInvalidNullableSubmissionsAreRejectedInsteadOfBecomingFalse(): void
	{
		$field = $this->field(nullable: true);
		$patch = new FormPatch([$field->properties()]);

		foreach (['maybe', [], ['true']] as $input) {
			$content = $patch->content([], ['flag' => ['value' => ['zxx' => $input]]]);
			$this->assertFalse($field->shape()->validate($content['flag'])->valid());
		}
	}

	private function field(bool $nullable = false, array $data = []): Checkbox
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$owner = $this->createStub(Owner::class);
		$owner->method('locale')->willReturn($locales->getDefault());
		$owner->method('defaultLocale')->willReturn($locales->getDefault());
		$owner->method('locales')->willReturn($locales);
		$field = new Checkbox('flag', $owner, new ValueContext('flag', $data));
		$field->init(Services::withDefaults());
		$field->nullable = $nullable;

		return $field;
	}

	public function testStateLabelsRejectUnrelatedFields(): void
	{
		$node = new class {
			#[StateLabels(true: 'Enabled')]
			public Text $flag;
		};

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('StateLabels requires a Checkbox field.');
		new FieldHydrator(Services::withDefaults())->hydrate($node, [], $this->createStub(Owner::class));
	}
}

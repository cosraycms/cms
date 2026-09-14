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
use Cosray\Schema\StateLabels;
use Cosray\Tests\TestCase;

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
			$this->assertSame(['true' => 'Ja', 'false' => 'Nein'], $node->flag->control()->array()['props']['labels']);
		} finally {
			Verba::deactivate();
		}

		$this->assertSame('field:yes', $node->flag->stateLabels->true);
	}

	public function testStateLabelsAreIncludedInSchemaTranslationScanning(): void
	{
		$node = new class {
			#[StateLabels(true: 'Enabled', false: 'Disabled')]
			public Checkbox $flag;
		};

		$messages = new SchemaScanner([$node::class])->scan();
		$this->assertSame(['Enabled', 'Disabled'], array_column($messages, 'id'));
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

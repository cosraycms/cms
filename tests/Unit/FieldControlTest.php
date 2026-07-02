<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field;
use Cosray\Field\Control;
use Cosray\Field\Owner;
use Cosray\Tests\TestCase;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class FieldControlTest extends TestCase
{
	public function testBuiltinControlNames(): void
	{
		$expected = [
			Field\Text::class => 'text',
			Field\Youtube::class => 'text',
			Field\Textarea::class => 'textarea',
			Field\Number::class => 'number',
			Field\Decimal::class => 'number',
			Field\Date::class => 'date',
			Field\Time::class => 'time',
			Field\DateTime::class => 'datetime',
			Field\Checkbox::class => 'checkbox',
			Field\Radio::class => 'option',
			Field\Option::class => 'option',
			Field\Code::class => 'code',
			Field\RichText::class => 'richtext',
			Field\Iframe::class => 'iframe',
			Field\Image::class => 'image',
			Field\File::class => 'file',
			Field\Video::class => 'video',
			Field\Blocks::class => 'blocks',
			Field\Entries::class => 'entries',
		];

		foreach ($expected as $class => $name) {
			$this->assertSame($name, $this->field($class)->control()->array()['name'], $class);
		}
	}

	public function testControlPropsSerialize(): void
	{
		$this->assertSame(
			['name' => 'number', 'props' => ['step' => 'any']],
			$this
				->field(Field\Decimal::class)
				->control()
				->array(),
		);
		$this->assertSame(
			['name' => 'option', 'props' => ['display' => 'radio']],
			$this
				->field(Field\Radio::class)
				->control()
				->array(),
		);
	}

	public function testPropertiesIncludeControl(): void
	{
		$properties = $this->field(Field\Text::class)->properties();

		$this->assertSame(['name' => 'text', 'props' => []], $properties['control']);
	}

	public function testStructuralControlsNestDescriptors(): void
	{
		$group = Control::group([
			['key' => 'amount', 'label' => 'Amount', 'control' => Control::number(step: 0.01)],
			['key' => 'currency', 'control' => Control::option()],
		]);

		$this->assertSame(
			[
				'name' => 'group',
				'props' => [
					'fields' => [
						[
							'key' => 'amount',
							'label' => 'Amount',
							'control' => ['name' => 'number', 'props' => ['step' => 0.01]],
						],
						[
							'key' => 'currency',
							'control' => ['name' => 'option', 'props' => ['display' => 'select']],
						],
					],
				],
			],
			$group->array(),
		);

		$repeater = Control::repeater(Control::text(), max: 5);

		$this->assertSame(
			[
				'name' => 'repeater',
				'props' => [
					'item' => ['name' => 'text', 'props' => []],
					'max' => 5,
				],
			],
			$repeater->array(),
		);
	}

	public function testElementControl(): void
	{
		$this->assertSame(
			['name' => 'element', 'props' => ['tag' => 'acme-color', 'module' => 'acme-shop/controls.js']],
			Control::element('acme-color', 'acme-shop/controls.js')->array(),
		);
	}

	public function testPropReturnsModifiedCopy(): void
	{
		$code = Control::code();
		$withSyntax = $code->prop('syntaxes', ['php']);

		$this->assertNotSame($code, $withSyntax);
		$this->assertSame([], $code->array()['props']);
		$this->assertSame(['syntaxes' => ['php']], $withSyntax->array()['props']);
	}

	/** @param class-string<Field\Field> $class */
	private function field(string $class): Field\Field
	{
		return new $class('test', $this->createStub(Owner::class), new ValueContext('test', []));
	}
}

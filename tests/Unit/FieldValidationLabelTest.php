<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\Field;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Tests\RichtextOwnerTestCase;
use Cosray\Value\ValueContext;

/**
 * Rules run on the entries of a field's value map, so an unlabelled shape
 * reports the map key — a locale — as the field's name. The base case is
 * covered here rather than per field type; each type only passes the label on.
 *
 * The base class is named for richtext but supplies a plain owner with two
 * locales, which is all this needs.
 *
 * @internal
 *
 * @coversNothing
 */
final class FieldValidationLabelTest extends RichtextOwnerTestCase
{
	private function title(bool $translate = false): Text
	{
		$field = new Text('title', $this->owner(), new ValueContext('title', []));
		$field->init(Services::withDefaults());
		$field->label('Titel')->required();

		if ($translate) {
			$field->translate();
		}

		return $field;
	}

	public function testMessagesNameTheFieldRatherThanTheLocaleKey(): void
	{
		$result = $this
			->title()
			->shape()
			->validate([
				'type' => Text::class,
				'value' => [Field::NEUTRAL_LOCALE => null],
			]);

		$this->assertFalse($result->valid());
		$this->assertStringStartsWith(
			'Titel ',
			$result->messages(['value', Field::NEUTRAL_LOCALE])[0] ?? '',
		);
	}

	public function testTranslatedMessagesAlsoNameTheLocale(): void
	{
		$result = $this
			->title(translate: true)
			->shape()
			->validate([
				'type' => Text::class,
				'value' => ['en' => null, 'de' => null],
			]);

		$this->assertFalse($result->valid());
		$this->assertStringStartsWith(
			'Titel (English)',
			$result->messages(['value', 'en'])[0] ?? '',
		);
	}
}

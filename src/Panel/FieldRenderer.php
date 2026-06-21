<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\Field\Blocks;
use Cosray\Field\Checkbox;
use Cosray\Field\Code;
use Cosray\Field\Date;
use Cosray\Field\Decimal;
use Cosray\Field\Entries;
use Cosray\Field\Field;
use Cosray\Field\File;
use Cosray\Field\Iframe;
use Cosray\Field\Image;
use Cosray\Field\Number;
use Cosray\Field\Option;
use Cosray\Field\Radio;
use Cosray\Field\RichText;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Field\Time;
use Cosray\Field\Video;
use Cosray\Renderer;

final class FieldRenderer
{
	private const string FALLBACK = 'field/unsupported';

	/** @var array<class-string<Field>, non-empty-string> */
	private const array DEFAULT_TEMPLATES = [
		Code::class => 'field/code',
		RichText::class => 'field/richtext',
		Textarea::class => 'field/textarea',
		Iframe::class => 'field/iframe',
		Text::class => 'field/text',
		Decimal::class => 'field/number',
		Number::class => 'field/number',
		Checkbox::class => 'field/checkbox',
		Radio::class => 'field/option',
		Option::class => 'field/option',
		Date::class => 'field/date',
		Time::class => 'field/time',
		Image::class => 'field/image',
		Video::class => 'field/video',
		File::class => 'field/file',
		Blocks::class => 'field/blocks',
		Entries::class => 'field/entries',
	];

	public function __construct(
		private readonly Renderer $renderer,
	) {}

	/** @param array<string, mixed> $context */
	public function render(Field $field, array $context = []): string
	{
		return $this->renderer->render(
			'field/shell',
			array_merge(
				$this->context($field),
				$context,
				['fieldView' => $this->template($field)],
			),
		);
	}

	/** @return array<string, mixed> */
	public function context(Field $field): array
	{
		$properties = $field->properties();
		$name = (string) $properties['name'];
		$type = (string) $properties['type'];
		$structure = array_replace_recursive($field->structure(), $field->data());

		return [
			'field' => $field,
			'properties' => $properties,
			'name' => $name,
			'type' => $type,
			'id' => $this->id($name),
			'inputId' => 'field-' . $this->id($name),
			'label' => $this->label($properties, $name),
			'description' => $properties['description'] ?? null,
			'required' => ($properties['required'] ?? false) === true,
			'hidden' => ($properties['hidden'] ?? false) === true,
			'translate' => ($properties['translate'] ?? false) === true,
			'translateMode' => $properties['translateMode'] ?? null,
			'structure' => $structure,
			'value' => $structure['value'] ?? null,
			'meta' => $structure['meta'] ?? [],
			'errors' => [],
		];
	}

	/** @return non-empty-string */
	public function template(Field $field): string
	{
		$class = $field::class;

		if (isset(self::DEFAULT_TEMPLATES[$class])) {
			return self::DEFAULT_TEMPLATES[$class];
		}

		foreach (self::DEFAULT_TEMPLATES as $type => $template) {
			if ($field instanceof $type) {
				return $template;
			}
		}

		return self::FALLBACK;
	}

	private function id(string $name): string
	{
		$id = preg_replace('/[^A-Za-z0-9_-]+/', '-', $name) ?? '';
		$id = trim($id, '-');

		return $id === '' ? 'field' : $id;
	}

	/** @param array<string, mixed> $properties */
	private function label(array $properties, string $name): string
	{
		$label = $properties['label'] ?? null;

		if (is_string($label) && trim($label) !== '') {
			return $label;
		}

		return ucfirst(str_replace(['_', '-'], ' ', $name));
	}
}

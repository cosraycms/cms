<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Block\RenderContext;
use Cosray\Field;
use Cosray\Field\Capability\Translatable;
use Cosray\Field\Owner;
use Cosray\Util\Html as HtmlUtil;
use Generator;

/**
 * @property-read Field\Blocks&Translatable $field
 */
class Blocks extends Value
{
	protected readonly ?Generator $preparedData;

	public function __construct(Owner $owner, Field\Blocks&Translatable $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		$this->preparedData = $this->prepareData($this->data);
	}

	public function __toString(): string
	{
		return $this->render();
	}

	public function json(): array
	{
		return $this->unwrap();
	}

	public function unwrap(): array
	{
		return [
			'columns' => $this->columns(),
			'data' => $this->preparedData,
		];
	}

	public function image(int $index = 1): ?Image
	{
		$i = 0;

		foreach ($this->preparedData as $value) {
			if ($value->type !== 'image') {
				continue;
			}

			$i++;

			if ($i === $index) {
				return new Field\Image(
					$this->context->fieldName,
					$this->owner,
					new ValueContext($this->context->fieldName, $this->mediaFieldData($value->data)),
				)
					->limit(1)
					->value();
			}
		}

		return null;
	}

	public function images(bool $all = false): Generator
	{
		if ($all && $this->field->isTranslatable()) {
			foreach ($this->data['value'] ?? [] as $data) {
				if (!is_array($data)) {
					continue;
				}

				foreach ($data as $value) {
					if (!is_array($value)) {
						continue;
					}

					$item = new Block((string) ($value['type'] ?? ''), $value);

					if ($item->type === 'image') {
						yield new Field\Image(
							$this->context->fieldName,
							$this->owner,
							new ValueContext($this->context->fieldName, $this->mediaFieldData($item->data)),
						)
							->limit(1)
							->value();
					} elseif ($item->type === 'images') {
						foreach (new Field\Image(
							$this->context->fieldName,
							$this->owner,
							new ValueContext($this->context->fieldName, $this->mediaFieldData($item->data)),
						)->value() as $image) {
							yield $image;
						}
					}
				}
			}
		} else {
			foreach ($this->preparedData as $item) {
				if ($item->type === 'image') {
					yield new Field\Image(
						$this->context->fieldName,
						$this->owner,
						new ValueContext($this->context->fieldName, $this->mediaFieldData($item->data)),
					)
						->limit(1)
						->value();
				} elseif ($item->type === 'images') {
					foreach (new Field\Image(
						$this->context->fieldName,
						$this->owner,
						new ValueContext($this->context->fieldName, $this->mediaFieldData($item->data)),
					)->value() as $image) {
						yield $image;
					}
				}
			}
		}
	}

	public function hasImage(int $index = 1): bool
	{
		$i = 0;

		foreach ($this->preparedData as $value) {
			if ($value->type !== 'image') {
				continue;
			}

			$i++;

			if ($i === $index) {
				return true;
			}
		}

		return false;
	}

	public function excerpt(
		int $words = 30,
		string $allowedTags = '',
		int $index = 1,
	): string {
		$i = 0;

		foreach ($this->preparedData as $value) {
			if ($value->type !== 'richtext') {
				continue;
			}

			$i++;

			if ($i === $index) {
				return HtmlUtil::excerpt((string) $this->blockValue($value), $words, $allowedTags);
			}
		}

		return '';
	}

	public function columns(): int
	{
		return (int) ($this->meta('columns', 12) ?: 12);
	}

	// Supported args:
	//
	// - prefix: All css classes are prefixed with this value. Default 'cms'
	// - tag: The tag of the container. Default 'div'
	// - class: An additional class added to the container
	// - imageSizes: `media.sizes` names forming the image block's srcset
	//   ladder. Default ['block-sm', 'block', 'block-lg']
	// - sizes: Template for the image block's `sizes` attribute; `{pct}`
	//   is replaced with the block's grid share in percent.
	//   Default '(min-width: 48rem) {pct}vw, 100vw'
	// - thumbSize: `media.sizes` name for gallery thumbs. Default 'block-thumb'
	public function render(mixed ...$args): string
	{
		$tag = $args['tag'] ?? 'div';
		$args['tag'] = $tag;
		$prefix = $args['prefix'] ?? 'cms';
		$args['prefix'] = $prefix;
		$class = $args['class'] ?? '';
		$class = $class !== '' ? ' ' . $class : '';
		$args['class'] = $class;

		$columns = $this->columns();

		$out =
			'<'
			. $tag
			. ' class="'
			. $prefix
			. '-blocks '
			. $prefix
			. '-blocks-columns-'
			. $columns
			. $class
			. '">';

		foreach ($this->preparedData as $value) {
			$out .= $this->renderValue($prefix, $value, $args);
		}

		$out .= '</' . $tag . '>';

		return $out;
	}

	public function isset(): bool
	{
		if ($this->preparedData === null) {
			return false;
		}

		$value = $this->data['value'] ?? null;

		if (!is_array($value)) {
			return false;
		}

		if ($this->field->isTranslatable()) {
			$defaultValue = $value[$this->defaultLocale->id] ?? [];

			return is_array($defaultValue) && count($defaultValue) > 0;
		}

		if (!isset($this->data['type']) && array_is_list($value)) {
			return count($value) > 0;
		}

		$defaultValue = $value[Field\Field::NEUTRAL_LOCALE] ?? [];

		return is_array($defaultValue) && count($defaultValue) > 0;
	}

	protected function renderValue(string $prefix, Block $value, array $args): string
	{
		$ctx = new RenderContext(
			$this->owner,
			$this->context->fieldName,
			$this->columns(),
			$args,
		);
		$rendered = $this->field->services()->blocks->get($value->type)->render($value, $ctx);

		// An empty render (e.g. an image block whose asset is gone) must
		// not occupy a grid cell; use `colstart` to position blocks.
		if ($rendered === '') {
			return '';
		}

		$colspan = $prefix . '-colspan-' . $value->data['colspan'];
		$rowspan = $prefix . '-rowspan-' . $value->data['rowspan'];
		$colstart = $value->data['colstart'] ?? null
			? $prefix . '-colstart-' . $value->data['colstart']
			: null;
		$styleClass = $value->styleClass();
		$class = $styleClass ? ' ' . $styleClass : '';

		return (
			'<div class="'
			. $prefix
			. '-'
			. $value->type
			. ' '
			. $colspan
			. ' '
			. $rowspan
			. ($colstart ? ' ' . $colstart : '')
			. $class
			. '">'
			. $rendered
			. '</div>'
		);
	}

	protected function prepareData(array $data): Generator
	{
		$fields = [];

		if ($this->field->isTranslatable()) {
			$value = $this->effective($data['value'] ?? []);
			$fields = is_array($value) ? $value : [];
		} elseif (
			!isset($data['type'])
			&& isset($data['value'])
			&& is_array($data['value'])
			&& array_is_list($data['value'])
		) {
			$fields = $data['value'];
		} else {
			$value = $data['value'][Field\Field::NEUTRAL_LOCALE] ?? [];
			$fields = is_array($value) ? $value : [];
		}

		foreach ($fields as $field) {
			if (!is_array($field) || !is_string($field['type'] ?? null)) {
				continue;
			}

			yield new Block($field['type'], $field);
		}
	}

	private function blockValue(Block $block): string
	{
		$value = $block->data['value'] ?? [];

		if (!is_array($value)) {
			return is_string($value) || is_numeric($value) ? (string) $value : '';
		}

		$value = $this->effective($value);

		return is_string($value) || is_numeric($value) ? (string) $value : '';
	}

	private function mediaFieldData(array $data): array
	{
		$data['value'] = [Field\Field::NEUTRAL_LOCALE => $data['value'] ?? $data['files'] ?? []];

		return $data;
	}
}

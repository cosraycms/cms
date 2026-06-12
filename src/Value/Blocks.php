<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets\ResizeMode;
use Cosray\Assets\Size;
use Cosray\Field;
use Cosray\Field\Capability\Translatable;
use Cosray\Field\Owner;
use Cosray\Util\Html as HtmlUtil;
use Generator;
use Gumlet\ImageResize;

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
			'columns' => $this->data['columns'] ?? null,
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
					new ValueContext($this->context->fieldName, $value->data),
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
				foreach ($data as $value) {
					$item = new BlockItem($value['type'], $value);

					if ($item->type === 'image') {
						yield new Field\Image(
							$this->context->fieldName,
							$this->owner,
							new ValueContext($this->context->fieldName, $item->data),
						)
							->limit(1)
							->value();
					} elseif ($item->type === 'images') {
						foreach (new Field\Image(
							$this->context->fieldName,
							$this->owner,
							new ValueContext($this->context->fieldName, $item->data),
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
						new ValueContext($this->context->fieldName, $item->data),
					)
						->limit(1)
						->value();
				} elseif ($item->type === 'images') {
					foreach (new Field\Image(
						$this->context->fieldName,
						$this->owner,
						new ValueContext($this->context->fieldName, $item->data),
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
				return HtmlUtil::excerpt($value->data['value'] ?? '', $words, $allowedTags);
			}
		}

		return '';
	}

	public function columns(): int
	{
		return (int) ($this->data['columns'] ?? 12);
	}

	// Supported args:
	//
	// - prefix: All css classes are prefixed with this value. Default 'cms'
	// - tag: The tag of the container. Default 'div'
	// - maxImageWidth: The maximum width of images. Images will be resized according to colspan. Default: 1280
	// - class: An additional class added to the container
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

		if (!($this->data['value'] ?? null)) {
			return false;
		}

		if ($this->field->isTranslatable()) {
			return count($this->data['value'][$this->defaultLocale->id]) > 0;
		}

		return count($this->data['value']) > 0;
	}

	protected function renderValue(string $prefix, BlockItem $value, array $args): string
	{
		$colspan = $prefix . '-colspan-' . $value->data['colspan'];
		$rowspan = $prefix . '-rowspan-' . $value->data['rowspan'];
		$colstart = $value->data['colstart'] ?? null
			? $prefix . '-colstart-' . $value->data['colstart']
			: null;
		$styleClass = $value->styleClass();
		$class = $styleClass ? ' ' . $styleClass : '';

		$out =
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
			. '">';
		$out .= match ($value->type) {
			'richtext' => $value->data['value'],
			'text' => $value->data['value'],
			'h1' => '<h1>' . $value->data['value'] . '</h1>',
			'h2' => '<h2>' . $value->data['value'] . '</h2>',
			'h3' => '<h3>' . $value->data['value'] . '</h3>',
			'h4' => '<h4>' . $value->data['value'] . '</h4>',
			'h5' => '<h5>' . $value->data['value'] . '</h5>',
			'h6' => '<h6>' . $value->data['value'] . '</h6>',
			'iframe' => $value->data['value'],
			'image' => $this->renderImage($value->data, $args),
			'images' => $this->renderImages($value->data, $args),
			'youtube' => $this->getValueObject(Field\Youtube::class, $value)->__toString(),
			'video' => $this->getValueObject(Field\Video::class, $value)->__toString(),
		};
		$out .= '</div>';

		return $out;
	}

	protected function getValueObject(string $class, BlockItem $item): Value
	{
		return new $class(
			$this->context->fieldName,
			$this->owner,
			new ValueContext($this->context->fieldName, $item->data),
		)->value();
	}

	protected function renderImage(array $data, array $args): string
	{
		$file = $data['files'][0]['file'];
		$title = $data['files'][0]['title'] ?? '';
		$maxWidth = $args['maxImageWidth'] ?? 1440;
		$path = $this->assetsPath() . $file;
		$image = $this->getAssets()->image($path);
		$resized = $image->resize(
			new Size((int) ($maxWidth / $this->columns()) * (int) ($data['colspan'] ?? 12)),
			ResizeMode::Width,
			enlarge: false,
			quality: null,
		);
		$url = $resized->url(true);

		return "<img src=\"{$url}\" alt=\"{$title}\" data-path-original=\"{$path}\">";
	}

	protected function renderImages(array $data): string
	{
		$result = '';

		foreach ($data['files'] as $f) {
			$file = $f['file'];
			$title = $f['title'] ?? '';
			$path = $this->assetsPath() . $file;
			$image = $this->getAssets()->image($path);
			$resized = $image->resize(
				new Size(400, 267, cropMode: ImageResize::CROPCENTER),
				ResizeMode::Crop,
				enlarge: false,
				quality: null,
			);
			$url = $resized->url(true);

			$result .= "<div class=\"cms-blocks-images-image\"><img src=\"{$url}\" alt=\"{$title}\" data-path-original=\"{$path}\"></div>";
		}

		if ($result) {
			return '<div class="cms-blocks-images">' . $result . '</div>';
		}

		return '';
	}

	protected function prepareData(array $data): Generator
	{
		if ($this->field->isTranslatable()) {
			$locale = $this->locale;

			while ($locale) {
				$fields = $data['value'][$locale->id] ?? null;

				if ($fields && count($fields) > 0) {
					break;
				}

				$locale = $locale->fallback();
			}
		} else {
			$fields = $data['value'];
		}

		foreach ($fields as $field) {
			yield new BlockItem($field['type'], $field);
		}
	}
}

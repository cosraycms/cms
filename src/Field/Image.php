<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Schema\TranslateMode;
use Cosray\Validation\Shapes;
use Cosray\Value;
use Override;

class Image extends File
{
	/** The tile aspect ratios a gallery may choose; `auto` keeps each image's own. */
	public const array RATIOS = ['auto', '1/1', '4/3', '3/2', '16/9', '3/4', '2/3'];

	#[Override]
	public function control(): Control
	{
		return Control::image();
	}

	/** @return list<TranslateMode> */
	#[Override]
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

	#[Override]
	public function value(): Value\Images|Value\Image
	{
		if ($this->allowsMultipleItems()) {
			if ($this->isAsymmetricallyTranslated()) {
				return new Value\TranslatedImages($this->owner, $this, $this->valueContext);
			}

			return new Value\Images($this->owner, $this, $this->valueContext);
		}

		if ($this->isAsymmetricallyTranslated()) {
			return new Value\TranslatedImage($this->owner, $this, $this->valueContext);
		}

		return new Value\Image($this->owner, $this, $this->valueContext);
	}

	#[Override]
	public function structure(mixed $value = null): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			return $this->getTranslatableFileStructure('image', $value);
		}

		return $this->getFileStructure('image', $value);
	}

	/**
	 * The gallery settings the image element keeps as field meta: the
	 * tiles' aspect ratio and whether they crop to it. Other meta keys
	 * pass through as before.
	 */
	#[Override]
	protected function metaShape(): Shape
	{
		$shape = parent::metaShape();
		$shape
			->add('ratio', $this->neutralShape('string', ['in:' . implode(',', self::RATIOS)]))
			->optional()
			->nullable();
		$shape->add('crop', $this->neutralShape('bool'))->optional()->nullable();

		return $shape;
	}

	/** @param list<string> $rules */
	private function neutralShape(string $type, array $rules = []): Shape
	{
		$shape = Shapes::create();
		$shape->add(self::NEUTRAL_LOCALE, $type)->rules(...$rules)->optional()->nullable();

		return $shape;
	}
}

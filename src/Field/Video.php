<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Schema\TranslateMode;
use Cosray\Value;
use Override;

class Video extends File implements Capability\Captionable
{
	use Capability\IsCaptionable;

	#[Override]
	public function control(): Control
	{
		return Control::video();
	}

	/** @return list<TranslateMode> */
	#[Override]
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

	#[Override]
	protected function itemMetaShape(): Shape
	{
		$shape = parent::itemMetaShape();
		$shape->add('poster', 'string')->optional()->nullable();

		return $shape;
	}

	#[Override]
	public function value(): Value\Video
	{
		return new Value\Video($this->owner, $this, $this->valueContext);
	}

	#[Override]
	public function structure(mixed $value = null): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			return $this->getTranslatableFileStructure('video', $value);
		}

		return $this->getFileStructure('video', $value);
	}
}

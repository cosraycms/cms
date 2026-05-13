<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

use Celemas\Sire\Contract\Validator;
use Celemas\Sire\Extra;
use Celemas\Sire\Result;
use Celemas\Sire\Review;
use Celemas\Sire\Shape;
use Override;

final class GridItemValidator implements Validator
{
	private Shape $shape;

	public function __construct(bool $list = false, bool $keepUnknown = false, ?string $title = null)
	{
		unset($title);

		$this->shape = $list ? Shape::list() : new Shape();
		$this->shape->rules(Validators::registry());

		if ($keepUnknown) {
			$this->shape->extra(Extra::Allow);
		}

		$this->shape
			->add('type', 'string')
			->rules('required', 'in:text,richtext,image,youtube,images,video,iframe');
		$this->shape->add('rowspan', 'int')->rules('required');
		$this->shape->add('colspan', 'int')->rules('required');
		$this->shape->add('colstart', 'int')->optional()->nullable();
		$this->shape->review($this->reviewItems(...));
	}

	#[Override]
	public function validate(array $data): Result
	{
		return $this->shape->validate($data);
	}

	private function reviewItems(Review $review): void
	{
		foreach ($review->values() as $index => $value) {
			$listIndex = $review->isList() && is_int($index) ? $index : null;
			$type = is_array($value) ? $value['type'] ?? null : null;

			if ($type === 'image' || $type === 'images' || $type === 'video') {
				$files = $value['files'] ?? [];

				if (is_array($files) && count($files) > 0) {
					$fileShape = Shapes::list();
					$fileShape->add('file', 'string')->rules('required');
					$fileShape->add('title', 'string')->optional()->nullable();
					$fileShape->add('alt', 'string')->optional()->nullable();

					if ($fileShape->validate($files)->valid()) {
						continue;
					}

					$this->addError(
						$review,
						$listIndex,
						'image',
						_('Attribute `file` nicht gefüllt.'),
					);

					continue;
				}

				$this->addError(
					$review,
					$listIndex,
					'image',
					_('Bild eingefügt aber nicht hochgeladen.'),
				);
			} elseif ($type === 'youtube') {
				if (!($value['value'] ?? null)) {
					$this->addError(
						$review,
						$listIndex,
						'value',
						_('Bitte gültige Youtube-ID eingeben.'),
					);
				}

				$aspectRatioX = $value['aspectRatioX'] ?? null;

				if (!$aspectRatioX || !is_numeric($aspectRatioX)) {
					$this->addError(
						$review,
						$listIndex,
						'aspectRatioX',
						_('Bitte gültige Zahl eingeben.'),
					);
				}

				$aspectRatioY = $value['aspectRatioY'] ?? null;

				if (!$aspectRatioY || !is_numeric($aspectRatioY)) {
					$this->addError(
						$review,
						$listIndex,
						'aspectRatioY',
						_('Bitte gültige Zahl eingeben.'),
					);
				}
			} elseif ($type === 'richtext' || $type === 'text') {
				if (!($value['value'] ?? null)) {
					$this->addError(
						$review,
						$listIndex,
						'value',
						_('Bitte Textfeld ausfüllen oder Block löschen.'),
					);
				}
			} elseif ($type === 'iframe') {
				if (!($value['value'] ?? null)) {
					$this->addError(
						$review,
						$listIndex,
						'value',
						_('Bitte Iframe-Feld ausfüllen oder Block löschen.'),
					);
				}
			}
		}
	}

	private function addError(
		Review $review,
		?int $listIndex,
		string $field,
		string $message,
	): void {
		$review->addError($listIndex === null ? $field : [$listIndex, $field], $message);
	}
}

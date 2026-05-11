<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

use Celemas\Sire\Contract\Shape as ShapeContract;
use Celemas\Sire\Result;
use Celemas\Sire\Review;
use Celemas\Sire\Shape;
use Override;

final class GridItemValidator implements ShapeContract
{
	private Shape $shape;

	public function __construct(bool $list = false, bool $keepUnknown = false, ?string $title = null)
	{
		$this->shape = $list ? Shape::list() : new Shape();
		$this->shape->keepUnknown($keepUnknown)->title($title);
		$this->shape->add(
			'type',
			'text',
			'required',
			'in:text,richtext,image,youtube,images,video,iframe',
		);
		$this->shape->add('rowspan', 'int', 'required');
		$this->shape->add('colspan', 'int', 'required');
		$this->shape->add('colstart', 'int');
		$this->shape->review($this->reviewItems(...));
	}

	#[Override]
	public function validate(array $data, int $level = 1): Result
	{
		return $this->shape->validate($data, $level);
	}

	private function reviewItems(Review $review): void
	{
		foreach ($review->values() as $index => $value) {
			$listIndex = $review->isList() && is_int($index) ? $index : null;
			$type = is_array($value) ? $value['type'] ?? null : null;

			if ($type === 'image' || $type === 'images' || $type === 'video') {
				$files = $value['files'] ?? [];

				if (is_array($files) && count($files) > 0) {
					$fileShape = Shape::list()->title(_('Grid Bild'))->keepUnknown();
					$fileShape->add('file', 'text', 'required');
					$fileShape->add('title', 'text');
					$fileShape->add('alt', 'text');

					if (!$fileShape->validate($files)->isValid()) {
						$review->addError('image', _('Grid Bild'), _('Attribute `file` nicht gefüllt.'), $listIndex);
					}

					continue;
				}

				$review->addError(
					'image',
					_('Grid Bild'),
					_('Bild eingefügt aber nicht hochgeladen.'),
					$listIndex,
				);
			} elseif ($type === 'youtube') {
				if (!($value['value'] ?? null)) {
					$review->addError(
						'value',
						_('Youtube-ID'),
						_('Bitte gültige Youtube-ID eingeben.'),
						$listIndex,
					);
				}

				$aspectRatioX = $value['aspectRatioX'] ?? null;

				if (!$aspectRatioX || !is_numeric($aspectRatioX)) {
					$review->addError(
						'aspectRatioX',
						_('Youtube Seitenverhältnis Breite'),
						_('Bitte gültige Zahl eingeben.'),
						$listIndex,
					);
				}

				$aspectRatioY = $value['aspectRatioY'] ?? null;

				if (!$aspectRatioY || !is_numeric($aspectRatioY)) {
					$review->addError(
						'aspectRatioY',
						_('Youtube Seitenverhältnis Höhe'),
						_('Bitte gültige Zahl eingeben.'),
						$listIndex,
					);
				}
			} elseif ($type === 'richtext' || $type === 'text') {
				if (!($value['value'] ?? null)) {
					$review->addError(
						'value',
						_('Grid Text'),
						_('Bitte Textfeld ausfüllen oder Block löschen.'),
						$listIndex,
					);
				}
			} elseif ($type === 'iframe') {
				if (!($value['value'] ?? null)) {
					$review->addError(
						'value',
						_('Grid Text'),
						_('Bitte Iframe-Feld ausfüllen oder Block löschen.'),
						$listIndex,
					);
				}
			}
		}
	}
}

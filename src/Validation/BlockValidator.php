<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celemas\Sire\Contract\Validator;
use Celemas\Sire\Extra;
use Celemas\Sire\Result;
use Celemas\Sire\Review;
use Celemas\Sire\Shape;
use Cosray\Richtext\Envelope;
use Cosray\Richtext\Validator as RichtextValidator;
use Override;

final class BlockValidator implements Validator
{
	private Shape $shape;
	private readonly RichtextValidator $richtext;

	/**
	 * @param array<string, string> $richtextClasses
	 * @param array<string, string> $richtextStyles
	 */
	public function __construct(
		bool $list = false,
		bool $keepUnknown = false,
		?string $title = null,
		array $richtextClasses = [],
		array $richtextStyles = [],
	) {
		unset($title);

		$this->richtext = new RichtextValidator($richtextClasses, $richtextStyles);
		$this->shape = $list ? Shape::list() : new Shape();
		$this->shape->rules(Validators::registry());

		if ($keepUnknown) {
			$this->shape->extra(Extra::Allow);
		}

		$this->shape
			->add('type', 'string')
			->rules('required', 'in:text,richtext,h1,h2,h3,h4,h5,h6,image,youtube,images,video,iframe');
		$this->shape->add('rowspan', 'int')->rules('required');
		$this->shape->add('colspan', 'int')->rules('required');
		$this->shape->add('width', 'int')->optional()->nullable();
		$this->shape->add('colstart', 'int')->optional()->nullable();
		$this->shape->add('meta', Shapes::create()->extra(Extra::Allow))->optional()->nullable();
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
				$this->reviewMedia($review, $listIndex, $value);
			} elseif ($type === 'youtube') {
				$this->reviewYoutube($review, $listIndex, $value);
			} elseif ($type === 'richtext') {
				$this->reviewRichtext($review, $listIndex, $value);
			} elseif (in_array($type, ['text', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
				if (!$this->hasZxxValue($value)) {
					$this->addError(
						$review,
						$listIndex,
						'value',
						_('Bitte Textfeld ausfüllen oder Block löschen.'),
					);
				}
			} elseif ($type === 'iframe') {
				if (!$this->hasZxxValue($value)) {
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

	/**
	 * Richtext blocks are writer-strict like richtext fields: the
	 * structured envelope is required, every non-null locale document
	 * must pass the format validation.
	 */
	private function reviewRichtext(Review $review, ?int $listIndex, mixed $value): void
	{
		if (!is_array($value) || ($value['format'] ?? null) !== Envelope::FORMAT) {
			$this->addError(
				$review,
				$listIndex,
				'format',
				_('Formatierter Text muss im strukturierten Format übertragen werden.'),
			);

			return;
		}

		if (($value['version'] ?? null) !== Envelope::VERSION) {
			$this->addError($review, $listIndex, 'version', _('Unbekannte Formatversion.'));

			return;
		}

		$hasContent = false;

		foreach (is_array($value['value'] ?? null) ? $value['value'] : [] as $doc) {
			if ($doc === null) {
				continue;
			}

			$hasContent = true;

			foreach ($this->richtext->validate($doc) as $error) {
				$this->addError($review, $listIndex, 'value', $error);
			}
		}

		if (!$hasContent) {
			$this->addError(
				$review,
				$listIndex,
				'value',
				_('Bitte Textfeld ausfüllen oder Block löschen.'),
			);
		}
	}

	private function reviewMedia(Review $review, ?int $listIndex, mixed $value): void
	{
		$files = is_array($value) ? $value['value'] ?? [] : [];

		if (is_array($files) && count($files) > 0) {
			$fileShape = Shapes::list();
			$fileShape->add('uid', 'string')->rules('required');
			$fileShape->add('meta', Shapes::create()->extra(Extra::Allow))->optional()->nullable();

			if ($fileShape->validate($files)->valid()) {
				return;
			}

			$this->addError(
				$review,
				$listIndex,
				'image',
				_('Attribute `uid` nicht gefüllt.'),
			);

			return;
		}

		$this->addError(
			$review,
			$listIndex,
			'image',
			_('Bild eingefügt aber nicht hochgeladen.'),
		);
	}

	private function reviewYoutube(Review $review, ?int $listIndex, mixed $value): void
	{
		if (!$this->hasZxxValue($value)) {
			$this->addError(
				$review,
				$listIndex,
				'value',
				_('Bitte gültige Youtube-ID eingeben.'),
			);
		}

		$aspectRatioX = $value['meta']['aspectRatioX']['zxx'] ?? null;

		if (!$aspectRatioX || !is_numeric($aspectRatioX)) {
			$this->addError(
				$review,
				$listIndex,
				'aspectRatioX',
				_('Bitte gültige Zahl eingeben.'),
			);
		}

		$aspectRatioY = $value['meta']['aspectRatioY']['zxx'] ?? null;

		if (!$aspectRatioY || !is_numeric($aspectRatioY)) {
			$this->addError(
				$review,
				$listIndex,
				'aspectRatioY',
				_('Bitte gültige Zahl eingeben.'),
			);
		}
	}

	private function hasZxxValue(mixed $value): bool
	{
		$value = is_array($value) ? $value['value']['zxx'] ?? null : null;

		return $value !== null && $value !== '';
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

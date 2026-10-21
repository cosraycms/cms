<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celema\Sire\Contract\Validator;
use Celema\Sire\Extra;
use Celema\Sire\Result;
use Celema\Sire\Review;
use Celema\Sire\Shape;
use Cosray\Richtext\Validator as DocValidator;
use Override;

/**
 * Sire adapter running the writer-strict richtext document validation
 * on a per-locale value of a richtext field or block.
 */
final class RichtextDoc implements Validator
{
	private readonly Shape $shape;
	private readonly DocValidator $validator;

	/**
	 * @param array<string, string> $classes
	 * @param array<string, string> $styles
	 */
	public function __construct(array $classes = [], array $styles = [])
	{
		$this->validator = new DocValidator($classes, $styles);
		$this->shape = new Shape()
			->rules(Validators::registry())
			->extra(Extra::Allow);
		$this->shape->review($this->review(...));
	}

	#[Override]
	public function validate(array $data): Result
	{
		return $this->shape->validate($data);
	}

	private function review(Review $review): void
	{
		foreach ($this->validator->validate($review->values()) as $error) {
			$review->addError('', $error, 'richtext');
		}
	}
}

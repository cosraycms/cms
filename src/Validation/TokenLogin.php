<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celemas\Sire\Contract\Validator;
use Celemas\Sire\Result;
use Celemas\Sire\Shape;
use Override;

final class TokenLogin implements Validator
{
	private Shape $shape;

	public function __construct()
	{
		$this->shape = new Shape();
		$this->shape->add('token', 'string')->rules('required', 'maxlen:512')->label(__('auth:token'));
	}

	#[Override]
	public function validate(array $data): Result
	{
		return $this->shape->validate($data);
	}
}

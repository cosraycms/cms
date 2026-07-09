<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celemas\Sire\Contract\Validator;
use Celemas\Sire\Result;
use Celemas\Sire\Shape;
use Override;

final class Login implements Validator
{
	private Shape $shape;

	public function __construct()
	{
		$this->shape = new Shape();
		$this->shape
			->add('login', 'string')
			->rules('required', 'maxlen:254')
			->label(__('auth:login-label'));
		$this->shape
			->add('password', 'string')
			->rules('required', 'maxlen:512')
			->label(__('auth:password'));
		$this->shape
			->add('rememberme', 'bool')
			->empty('missing', 'null')
			->default(false)
			->label(__('auth:remember-me'));
	}

	#[Override]
	public function validate(array $data): Result
	{
		return $this->shape->validate($data);
	}
}

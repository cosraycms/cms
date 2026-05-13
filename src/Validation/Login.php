<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

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
			->label(_('Username or email'));
		$this->shape->add('password', 'string')->rules('required', 'maxlen:512')->label(_('Password'));
		$this->shape
			->add('rememberme', 'bool')
			->empty('missing', 'null')
			->default(false)
			->label(_('remember me'));
	}

	#[Override]
	public function validate(array $data): Result
	{
		return $this->shape->validate($data);
	}
}

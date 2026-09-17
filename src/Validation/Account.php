<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celema\Sire\Contract\Validator;
use Celema\Sire\Result;
use Celema\Sire\Shape;
use Override;

final class Account implements Validator
{
	private Shape $shape;

	public function __construct(bool $passwordRequired)
	{
		$this->shape = new Shape();
		$this->shape
			->add('email', 'string')
			->rules('required', 'email', 'maxlen:254')
			->label(__('user:email'));
		// Without an @, so a username can never equal another account's email:
		// the login form matches a login against both.
		$this->shape
			->add('username', 'string')
			->rules('maxlen:64', 'regex:/^[^@\s]+$/')
			->optional()
			->nullable()
			->label(__('user:username'));
		$this->shape
			->add('name', 'string')
			->rules('maxlen:200')
			->optional()
			->nullable()
			->label(__('user:name'));
		$password = $this->shape
			->add('password', 'string')
			->rules('minlen:12', 'maxlen:512')
			->label(__('user:password'));

		if ($passwordRequired) {
			$password->rules('required');
		} else {
			$password->optional()->nullable();
		}
	}

	#[Override]
	public function validate(array $data): Result
	{
		return $this->shape->validate($data);
	}
}

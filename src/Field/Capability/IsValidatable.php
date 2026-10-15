<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsValidatable
{
	/** @var list<string> */
	protected array $validators = [];

	public function addValidators(string ...$validators): static
	{
		$this->validators = array_merge($this->validators, $validators);

		return $this;
	}

	/** @return list<string> */
	public function validators(): array
	{
		return array_values(array_unique($this->validators));
	}
}

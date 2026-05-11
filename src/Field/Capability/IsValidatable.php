<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

trait IsValidatable
{
	protected array $validators = [];

	public function addValidators(string ...$validators): static
	{
		$this->validators = array_merge($this->validators, $validators);

		return $this;
	}

	public function validators(): array
	{
		return array_values(array_unique($this->validators));
	}
}

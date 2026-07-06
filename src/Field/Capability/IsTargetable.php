<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsTargetable
{
	/** @var list<string> node classes or handles a reference may point at */
	protected array $targetTypes = [];

	public function target(string ...$types): static
	{
		$this->targetTypes = array_values(array_unique([...$this->targetTypes, ...$types]));

		return $this;
	}

	/** @return list<string> */
	public function targets(): array
	{
		return $this->targetTypes;
	}
}

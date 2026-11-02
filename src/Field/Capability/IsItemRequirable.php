<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsItemRequirable
{
	/** @var list<string> */
	protected array $requiredItems = [];

	public function requireItem(string $key): static
	{
		if (!in_array($key, $this->requiredItems, true)) {
			$this->requiredItems[] = $key;
		}

		return $this;
	}

	/** @return list<string> */
	public function requiredItems(): array
	{
		return $this->requiredItems;
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsFilterable
{
	protected ?string $filterQuery = null;

	public function filter(string $query): static
	{
		$query = trim($query);
		$this->filterQuery = $query === '' ? null : $query;

		return $this;
	}

	public function getFilter(): ?string
	{
		return $this->filterQuery;
	}
}

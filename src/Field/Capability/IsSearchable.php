<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

use Celemas\Cms\Schema\FulltextWeight;

trait IsSearchable
{
	public function fulltext(FulltextWeight $fulltextWeight): static
	{
		$this->fulltextWeight = $fulltextWeight;

		return $this;
	}

	public function getFulltextWeight(): ?FulltextWeight
	{
		return $this->fulltextWeight;
	}
}

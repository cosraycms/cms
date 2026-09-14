<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

use Cosray\Schema\FulltextWeight;

trait IsSearchable
{
	protected FulltextWeight|false|null $fulltextWeight = null;

	public function fulltext(FulltextWeight|false $fulltextWeight): static
	{
		$this->fulltextWeight = $fulltextWeight;

		return $this;
	}

	public function fulltextWeight(): FulltextWeight|false|null
	{
		return $this->fulltextWeight;
	}
}

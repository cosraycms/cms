<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

use Celemas\Cms\Schema\FulltextWeight;

interface Searchable
{
	public function fulltext(FulltextWeight $fulltextWeight): static;

	public function getFulltextWeight(): ?FulltextWeight;
}

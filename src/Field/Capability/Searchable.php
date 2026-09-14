<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

use Cosray\Schema\FulltextWeight;

interface Searchable
{
	public function fulltext(FulltextWeight|false $fulltextWeight): static;

	public function fulltextWeight(): FulltextWeight|false|null;
}

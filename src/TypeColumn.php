<?php

declare(strict_types=1);

namespace Cosray;

use Override;

/** The entry's type by its label, made by Column::type(). The search matches it unless told otherwise. */
final class TypeColumn extends TextColumn
{
	public function __construct(string $title)
	{
		parent::__construct($title, 'meta.name');
	}

	#[Override]
	protected function searchedByDefault(): array
	{
		return ['type'];
	}
}

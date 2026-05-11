<?php

declare(strict_types=1);

namespace Celemas\Cms;

final class CollectionListMeta
{
	public function __construct(
		public bool $showPublished = true,
		public bool $showLocked = false,
		public bool $showHidden = false,
		public bool $showChildren = false,
	) {}

	public function array(): array
	{
		return [
			'showPublished' => $this->showPublished,
			'showLocked' => $this->showLocked,
			'showHidden' => $this->showHidden,
			'showChildren' => $this->showChildren,
		];
	}
}

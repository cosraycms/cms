<?php

declare(strict_types=1);

namespace Cosray\Panel\Dashboard;

final readonly class Card
{
	public function __construct(
		public string $label,
		public int|string $value,
		public ?string $note = null,
		public ?string $url = null,
	) {}
}

<?php

declare(strict_types=1);

namespace Cosray\Value;

class Block
{
	public function __construct(
		public readonly string $type,
		public readonly array $data,
	) {}

	public function styleClass(): ?string
	{
		return $this->data['class'] ?? null;
	}

	public function elementId(): ?string
	{
		return $this->data['id'] ?? null;
	}
}

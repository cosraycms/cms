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
		$value = $this->meta('class');

		return is_string($value) && $value !== '' ? $value : null;
	}

	public function elementId(): ?string
	{
		$value = $this->meta('id');

		return is_string($value) && $value !== '' ? $value : null;
	}

	public function meta(string $key): mixed
	{
		return $this->data['meta'][$key]['zxx'] ?? null;
	}
}

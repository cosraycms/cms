<?php

declare(strict_types=1);

namespace Cosray\Assets;

final readonly class IngestResult
{
	/** @param array<mixed> $row */
	public function __construct(
		public array $row,
		public bool $created,
	) {}

	public function uid(): string
	{
		return (string) $this->row['uid'];
	}
}

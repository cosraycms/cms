<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

final readonly class Document
{
	public string $source;

	/** @param list<array{field: string, weight: string, text: string}> $contributions */
	public function __construct(
		public array $contributions,
		public bool $missingTitle = false,
	) {
		$this->source = implode("\n\n", array_column($contributions, 'text'));
	}
}

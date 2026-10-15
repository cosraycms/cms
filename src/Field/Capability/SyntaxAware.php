<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface SyntaxAware
{
	/** @param array<string> $syntaxes */
	public function syntaxes(array $syntaxes): void;

	/** @return list<string> */
	public function getSyntaxes(): array;

	public function getDefaultSyntax(): string;
}

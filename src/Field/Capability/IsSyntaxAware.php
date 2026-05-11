<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

trait IsSyntaxAware
{
	protected array $syntaxes = ['plaintext'];

	public function syntaxes(array $syntaxes): void
	{
		$values = [];

		foreach ($syntaxes as $syntax) {
			$syntax = trim($syntax);

			if ($syntax === '') {
				continue;
			}

			if (in_array($syntax, $values, true)) {
				continue;
			}

			$values[] = $syntax;
		}

		$this->syntaxes = $values ?: ['plaintext'];
	}

	public function getSyntaxes(): array
	{
		return $this->syntaxes;
	}

	public function getDefaultSyntax(): string
	{
		return $this->syntaxes[0] ?? 'plaintext';
	}
}

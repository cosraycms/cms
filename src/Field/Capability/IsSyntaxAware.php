<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

use Cosray\Exception\RuntimeException;
use Cosray\Field\CodeSyntaxes;

trait IsSyntaxAware
{
	protected array $syntaxes = [CodeSyntaxes::DEFAULT];

	/**
	 * Aliases become the syntax they stand for, so the panel and stored
	 * values only ever see the keys. A syntax the panel's editor lacks is a
	 * schema error; it would otherwise show up as plain text.
	 */
	public function syntaxes(array $syntaxes): void
	{
		$values = [];

		foreach ($syntaxes as $syntax) {
			if (trim($syntax) === '') {
				continue;
			}

			$key = CodeSyntaxes::resolve($syntax) ?? throw new RuntimeException(
				"The field \"{$this->name}\" offers the syntax \"{$syntax}\", which the panel lacks. "
					. 'Supported: '
					. implode(', ', CodeSyntaxes::KEYS)
					. '.',
			);

			if (!in_array($key, $values, true)) {
				$values[] = $key;
			}
		}

		$this->syntaxes = $values ?: [CodeSyntaxes::DEFAULT];
	}

	public function getSyntaxes(): array
	{
		return $this->syntaxes;
	}

	public function getDefaultSyntax(): string
	{
		return $this->syntaxes[0] ?? CodeSyntaxes::DEFAULT;
	}
}

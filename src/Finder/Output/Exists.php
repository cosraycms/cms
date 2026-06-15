<?php

declare(strict_types=1);

namespace Cosray\Finder\Output;

use Cosray\Context;
use Cosray\Exception\ParserOutputException;
use Cosray\Finder\Input\Token;

final readonly class Exists extends Expression implements Output
{
	public function __construct(
		#[\SensitiveParameter]
		private Token $token,
		private Context $context,
	) {}

	public function get(): string
	{
		if ($this->token->lexeme === '') {
			throw new ParserOutputException($this->token, 'Invalid field name in exists condition.');
		}

		return 'n.content @? '
		. $this->context->db->quote(
			'$.' . $this->fieldPath($this->token->lexeme),
		);
	}

	private function fieldPath(string $field): string
	{
		$parts = explode('.', $field);

		foreach ($parts as $part) {
			if ($part === '') {
				throw new ParserOutputException($this->token, 'Invalid field name in exists condition.');
			}
		}

		if (count($parts) === 1) {
			return $parts[0] . '.value.*';
		}

		if (count($parts) === 2 && $parts[1] === '?') {
			return $parts[0] . '.value.' . $this->context->localeId();
		}

		if (count($parts) > 2 && in_array('?', $parts, true)) {
			throw new ParserOutputException(
				$this->token,
				'The questionmark is allowed after the first dot only.',
			);
		}

		if (count($parts) === 2 && $parts[1] === '*') {
			return $parts[0] . '.value.*';
		}

		if (count($parts) === 2) {
			return $parts[0] . '.value.' . $parts[1];
		}

		return implode('.', $parts);
	}
}

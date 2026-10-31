<?php

declare(strict_types=1);

namespace Cosray\Panel;

/**
 * Per-browser panel choices that change how a page renders, so the server
 * reads them and a page arrives in its state. The panel's scripts write them
 * as `key=value` pairs (`panel/src/lib/panel-cookie.js`); the browser controls
 * the value, so only the choices listed here are read.
 */
final class Cookie
{
	public const string NAME = 'cosray_panel';

	private const array CHOICES = [
		'blocks' => ['wide'],
		'inspector' => ['collapsed'],
	];

	/** @param array<string, string> $choices */
	private function __construct(
		private readonly array $choices,
	) {}

	public static function parse(string $value): self
	{
		$choices = [];

		foreach (explode('&', $value) as $pair) {
			[$key, $choice] = array_pad(explode('=', $pair, 2), 2, '');

			if (in_array($choice, self::CHOICES[$key] ?? [], true)) {
				$choices[$key] = $choice;
			}
		}

		return new self($choices);
	}

	public function is(string $key, string $choice): bool
	{
		return ($this->choices[$key] ?? null) === $choice;
	}
}

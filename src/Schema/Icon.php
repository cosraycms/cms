<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;
use ValueError;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
readonly class Icon
{
	/** @var array<string, mixed> */
	public array $args;

	public function __construct(
		public string $id,
		mixed ...$args,
	) {
		$this->args = $this->parseArgs($args);
	}

	/**
	 * @param array<array-key, mixed> $args
	 * @return array<string, mixed>
	 */
	private function parseArgs(array $args): array
	{
		if (count($args) === 0) {
			return [];
		}

		if (count($args) === 1 && array_key_exists(0, $args) && is_array($args[0])) {
			if ($this->isStringMap($args[0])) {
				return $args[0];
			}

			throw new ValueError('Icon arguments must be an associative array or named arguments');
		}

		if ($this->isStringMap($args)) {
			return $args;
		}

		throw new ValueError('Icon arguments must be an associative array or named arguments');
	}

	/** @param array<array-key, mixed> $value */
	private function isStringMap(array $value): bool
	{
		foreach ($value as $key => $_) {
			if (!is_string($key)) {
				return false;
			}
		}

		return true;
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Icons;

use Override;

/**
 * The SVG icons plugins ship: `acme-shop:truck` is `truck.svg` in the
 * icon directory of the plugin `acme-shop`.
 */
final class Plugins implements Provider
{
	/** @var array<string, Local> */
	private array $dirs = [];

	/** @param array<string, string> $dirs plugin id => icon directory */
	public function __construct(array $dirs)
	{
		foreach ($dirs as $id => $dir) {
			$this->dirs[$id] = new Local([$dir]);
		}
	}

	/** @param array<array-key, mixed> $args */
	#[Override]
	public function icon(string $id, array $args = []): string
	{
		$colon = strpos($id, ':');

		if ($colon === false) {
			return '';
		}

		$local = $this->dirs[strtolower(trim(substr($id, 0, $colon)))] ?? null;

		return $local?->icon(substr($id, $colon + 1), $args) ?? '';
	}
}

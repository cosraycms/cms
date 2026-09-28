<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Core\Exception\HttpBadRequest;
use Cosray\Config;

/**
 * URL prefixes that the router or the web server answers before the node
 * catchall. A node path at or below one of them would never be reachable,
 * so it is refused when saved instead of silently shadowed.
 */
final readonly class ReservedPaths
{
	/** @var list<string> */
	private array $prefixes;

	/** @param list<string> $prefixes */
	public function __construct(array $prefixes = [])
	{
		$normalized = [];

		foreach ($prefixes as $prefix) {
			$prefix = '/' . trim($prefix, '/');

			// The root prefix would reserve every path; it is not a mount.
			if ($prefix !== '/') {
				$normalized[] = $prefix;
			}
		}

		$this->prefixes = array_values(array_unique($normalized));
	}

	public static function fromConfig(Config $config): self
	{
		return new self([
			$config->panel->path,
			$config->path->assets,
			$config->path->cache,
			'/preview',
		]);
	}

	/** The reserved prefix the path falls under, if any. */
	public function match(string $path): ?string
	{
		$path = '/' . ltrim($path, '/');

		foreach ($this->prefixes as $prefix) {
			if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
				return $prefix;
			}
		}

		return null;
	}

	/** @param array<array-key, mixed> $paths */
	public function assertFree(array $paths): void
	{
		foreach ($paths as $path) {
			if (!is_string($path) || trim($path) === '') {
				continue;
			}

			if ($this->match(trim($path)) !== null) {
				throw new HttpBadRequest(payload: [
					'message' => __('node:reserved-path', ['path' => '/' . ltrim(trim($path), '/')]),
				]);
			}
		}
	}
}

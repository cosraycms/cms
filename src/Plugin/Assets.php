<?php

declare(strict_types=1);

namespace Cosray\Plugin;

/**
 * Plugin asset directories, keyed by plugin id.
 *
 * Files are served path-jailed under the panel revision, at
 * `{panel}/assets/{revision}/plugins/{id}/...`, and unversioned under
 * `{panel}/vendor/{id}/...` for URLs built by hand.
 */
final class Assets
{
	/** @var array<string, string> */
	private array $dirs = [];

	public function add(string $id, string $dir): void
	{
		$this->dirs[$id] = rtrim($dir, '/');
	}

	public function dir(string $id): ?string
	{
		return $this->dirs[$id] ?? null;
	}

	/** @return array<string, string> plugin id => asset directory */
	public function all(): array
	{
		return $this->dirs;
	}
}

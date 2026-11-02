<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Cosray\Config;
use Generator;
use SplFileInfo;

/**
 * The rendition files on disk, as far as they can be told apart without looking
 * at content: a file is stale when no URL leads to it anymore, because its asset
 * is gone or replaced, or its size was changed or removed. A crop that a use no
 * longer has looks current, as telling would take reading all content.
 */
final class RenditionCache
{
	private readonly string $root;

	public function __construct(
		private readonly Config $config,
	) {
		$this->root = rtrim($config->path->public, '\\/') . '/' . trim($config->path->cache, '/');
	}

	/**
	 * Every file in the cache directory.
	 *
	 * @return Generator<SplFileInfo>
	 */
	public function files(): Generator
	{
		foreach (is_dir($this->root) ? (scandir($this->root) ?: []) : [] as $shard) {
			foreach ($this->entries("{$this->root}/{$shard}") as $uid) {
				foreach ($this->entries("{$this->root}/{$shard}/{$uid}") as $file) {
					$info = new SplFileInfo("{$this->root}/{$shard}/{$uid}/{$file}");

					if ($info->isFile()) {
						yield $info;
					}
				}
			}
		}
	}

	/**
	 * @param array<string, Asset> $assets The catalog by uid
	 */
	public function stale(SplFileInfo $file, array $assets): bool
	{
		$asset = $assets[basename($file->getPath())] ?? null;

		if ($asset === null) {
			return true;
		}

		$parts = $asset->renditionParts($file->getFilename());

		if ($parts === null) {
			return true;
		}

		$size = $parts[0];
		$sizes = $this->config->media->sizes;
		$spec =
			$size === Sizes::ORIGINAL && count($parts) === 4
				? Sizes::original()
				: ($sizes->has($size) ? $sizes->get($size) : null);

		return $spec === null || $spec->fingerprint() !== $parts[1];
	}

	/** Removes a file, and its directories once they are empty. */
	public function remove(SplFileInfo $file): void
	{
		$dir = $file->getPath();
		unlink($file->getPathname());

		foreach ([$dir, dirname($dir)] as $parent) {
			if ($parent === $this->root || (scandir($parent) ?: []) !== ['.', '..']) {
				return;
			}

			rmdir($parent);
		}
	}

	/** @return list<string> */
	private function entries(string $dir): array
	{
		if (!is_dir($dir) || basename($dir) === '.' || basename($dir) === '..') {
			return [];
		}

		return array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
	}
}

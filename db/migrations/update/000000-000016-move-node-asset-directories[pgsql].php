<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000016_MoveNodeAssetDirectories;

use Celemas\Quma\Contract;
use Celemas\Quma\Environment;
use Cosray\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class Migration implements Contract\Migration
{
	public function __construct(
		private readonly Config $config,
	) {}

	public function run(Environment $env): void
	{
		$assetBase = $this->basePath($this->config->path->assets) . '/node';
		$cacheBase = $this->basePath($this->config->path->cache) . '/node';
		$mappings = $env->db->execute($this->sql($env, '
			SELECT h.handle, n.uid
			FROM /*:cms.prefix:*/node_handles h
			INNER JOIN /*:cms.prefix:*/nodes n ON n.node = h.node
		'))->all();

		foreach ($mappings as $mapping) {
			$handle = (string) $mapping['handle'];
			$uid = (string) $mapping['uid'];

			$this->moveDirectory("{$assetBase}/{$handle}", "{$assetBase}/{$uid}");
			$this->deletePath("{$cacheBase}/{$handle}");
			$this->deletePath("{$cacheBase}/{$uid}");
		}
	}

	private function moveDirectory(string $source, string $target): void
	{
		if (!is_dir($source)) {
			return;
		}

		if (file_exists($target)) {
			if (!is_dir($target)) {
				throw new RuntimeException(
					"Cannot move '{$source}' because target '{$target}' is not a directory",
				);
			}

			$this->assertNoConflicts($source, $target);
			$this->mergeDirectory($source, $target);

			return;
		}

		$parent = dirname($target);

		if (!is_dir($parent) && !mkdir($parent, 0o755, true) && !is_dir($parent)) {
			throw new RuntimeException("Could not create asset directory '{$parent}'");
		}

		if (!rename($source, $target)) {
			throw new RuntimeException("Could not move asset directory '{$source}' to '{$target}'");
		}
	}

	private function assertNoConflicts(string $source, string $target): void
	{
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
		);
		$sourceLength = strlen(rtrim($source, '/')) + 1;

		foreach ($iterator as $item) {
			if (!$item instanceof SplFileInfo) {
				continue;
			}

			$relative = substr($item->getPathname(), $sourceLength);
			$destination = "{$target}/{$relative}";

			if ($item->isDir() && !$item->isLink()) {
				if (file_exists($destination) && !is_dir($destination)) {
					throw new RuntimeException("Asset move conflict at '{$destination}'");
				}

				continue;
			}

			if (file_exists($destination) || is_link($destination)) {
				throw new RuntimeException("Asset move conflict at '{$destination}'");
			}
		}
	}

	private function mergeDirectory(string $source, string $target): void
	{
		$items = scandir($source);

		if ($items === false) {
			throw new RuntimeException("Could not read asset directory '{$source}'");
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$sourcePath = "{$source}/{$item}";
			$targetPath = "{$target}/{$item}";

			if (is_dir($sourcePath) && !is_link($sourcePath)) {
				if (!is_dir($targetPath) && !mkdir($targetPath, 0o755, true) && !is_dir($targetPath)) {
					throw new RuntimeException("Could not create asset directory '{$targetPath}'");
				}

				$this->mergeDirectory($sourcePath, $targetPath);

				continue;
			}

			if (!rename($sourcePath, $targetPath)) {
				throw new RuntimeException("Could not move asset '{$sourcePath}' to '{$targetPath}'");
			}
		}

		if (!rmdir($source)) {
			throw new RuntimeException("Could not remove empty asset directory '{$source}'");
		}
	}

	private function deletePath(string $path): void
	{
		if (!file_exists($path) && !is_link($path)) {
			return;
		}

		if (is_file($path) || is_link($path)) {
			if (!unlink($path)) {
				throw new RuntimeException("Could not delete cache file '{$path}'");
			}

			return;
		}

		$items = scandir($path);

		if ($items === false) {
			throw new RuntimeException("Could not read cache directory '{$path}'");
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$this->deletePath("{$path}/{$item}");
		}

		if (!rmdir($path)) {
			throw new RuntimeException("Could not delete cache directory '{$path}'");
		}
	}

	private function basePath(string $path): string
	{
		return rtrim($this->config->path->public, '/') . '/' . trim($path, '/');
	}

	private function sql(Environment $env, string $sql): string
	{
		return $env->conn->applyPlaceholders($sql, __FILE__);
	}
}

return Migration::class;

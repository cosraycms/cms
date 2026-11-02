<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Quma\Database;
use Cosray\Assets\Asset;
use Cosray\Assets\Library;
use Cosray\Assets\RenditionCache;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Storage\Storage;

#[Command(
	'media:report',
	'Reports missing and leftover files, unused files, missing alt texts and images too large to resize',
	group: 'Media',
)]
final class MediaReport
{
	/** What GD takes per pixel of a decoded image. */
	private const int BYTES_PER_PIXEL = 5;

	public function __construct(
		private readonly Config $config,
		private readonly Database $db,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('The memory_limit of the web server, as this command\'s own is often another', value: 'limit')]
		string $memory = '',
	): int {
		$storage = new Storage($this->config);
		$library = new Library($this->db, $this->config);
		$assets = [];
		$missing = [];
		$keys = [];

		foreach ($this->db->assets->all()->all() as $row) {
			$asset = Asset::fromRow($row, $this->config);
			$assets[$asset->uid] = $asset;
			$keys[dirname($asset->key)] = true;

			try {
				$storage->path($asset->key);
			} catch (RuntimeException) {
				$missing[] = $asset;
			}
		}

		$io->line('Files in the catalog: %d', count($assets));
		$io->line('Not used anywhere: %d', $library->list([], '', null, 1, 0, 'unused')['total']);
		$io->line(
			'Images without an alt text in the catalog: %d',
			$library->list([], '', null, 1, 0, 'no-alt')['total'],
		);

		$io->line('Missing from the disk: %d', count($missing));

		foreach ($missing as $asset) {
			$io->line('  %s  %s', $asset->uid, $asset->filename);
		}

		$leftovers = $this->leftovers($keys);
		$io->line('On the disk without a catalog entry: %d', count($leftovers));

		foreach ($leftovers as $dir) {
			$io->line('  %s', $dir);
		}

		$this->tooLarge($io, $assets, $memory);
		$this->renditions($io, $assets);

		return $missing === [] && $leftovers === [] ? 0 : 1;
	}

	/**
	 * @param array<string, true> $keys
	 * @return list<string>
	 */
	private function leftovers(array $keys): array
	{
		$root = rtrim($this->config->path->public, '\\/') . '/' . trim($this->config->path->assets, '/');
		$found = [];

		foreach (is_dir($root) ? array_diff(scandir($root) ?: [], ['.', '..']) : [] as $shard) {
			if (!is_dir("{$root}/{$shard}")) {
				continue;
			}

			foreach (array_diff(scandir("{$root}/{$shard}") ?: [], ['.', '..']) as $uid) {
				if (!isset($keys["{$shard}/{$uid}"])) {
					$found[] = "{$shard}/{$uid}";
				}
			}
		}

		return $found;
	}

	/** @param array<string, Asset> $assets */
	private function tooLarge(Io $io, array $assets, string $memory): void
	{
		if ($this->config->media->backend !== 'gd') {
			return;
		}

		$limit = ini_parse_quantity($memory !== '' ? $memory : (string) ini_get('memory_limit'));

		if ($limit <= 0) {
			$io->line('Too large to resize: this process has no memory limit, so pass the server\'s with --memory.');

			return;
		}

		$large = array_filter(
			$assets,
			static fn(Asset $asset): bool => (
				$asset->resizable()
				&& ((int) $asset->width * (int) $asset->height * self::BYTES_PER_PIXEL) > $limit
			),
		);
		$io->line(
			'Too large to resize with a memory limit of %s: %d',
			number_format($limit / 1048576) . ' MB',
			count($large),
		);

		foreach ($large as $asset) {
			$io->line('  %s  %s  %d × %d', $asset->uid, $asset->filename, (int) $asset->width, (int) $asset->height);
		}
	}

	/** @param array<string, Asset> $assets */
	private function renditions(Io $io, array $assets): void
	{
		$cache = new RenditionCache($this->config);
		$files = 0;
		$bytes = 0;
		$stale = 0;

		foreach ($cache->files() as $file) {
			$files++;
			$bytes += $file->getSize();

			if ($cache->stale($file, $assets)) {
				$stale++;
			}
		}

		$io->line(
			'Renditions: %d, %s MB, of which no URL leads to %d',
			$files,
			number_format($bytes / 1048576, 1),
			$stale,
		);
	}
}

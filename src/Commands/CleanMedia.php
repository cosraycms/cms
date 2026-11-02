<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Quma\Database;
use Cosray\Assets\Asset;
use Cosray\Assets\RenditionCache;
use Cosray\Config;

#[Command(
	'media:clean',
	'Finds renditions no URL leads to anymore; --apply removes them, --all takes every rendition',
	group: 'Media',
)]
final class CleanMedia
{
	public function __construct(
		private readonly Config $config,
		private readonly Database $db,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Delete the files instead of counting them')]
		bool $apply = false,
		#[Opt('Take every rendition, which is generated again on its next request')]
		bool $all = false,
	): int {
		$cache = new RenditionCache($this->config);
		$assets = [];

		foreach ($this->db->assets->all()->all() as $row) {
			$assets[(string) $row['uid']] = Asset::fromRow($row, $this->config);
		}

		$files = 0;
		$bytes = 0;

		foreach (iterator_to_array($cache->files(), false) as $file) {
			if ($all || $cache->stale($file, $assets)) {
				$files++;
				$bytes += $file->getSize();

				if ($apply) {
					$cache->remove($file);
				}
			}
		}

		$size = number_format($bytes / 1_048_576, 1);
		$io->line($apply ? 'Removed %d rendition(s), %s MB.' : '%d rendition(s) to remove, %s MB.', $files, $size);

		if (!$apply && $files > 0) {
			$io->line('Run this again with <strong>--apply</strong> to remove them.');
		}

		return 0;
	}
}

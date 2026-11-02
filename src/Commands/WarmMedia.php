<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Quma\Database;
use Cosray\Assets\Asset;
use Cosray\Assets\Renditions;
use Cosray\Config;
use Cosray\Exception\RuntimeException;

#[Command(
	'media:warm',
	'Generates the renditions of every image, so no visitor waits for them',
	group: 'Media',
)]
final class WarmMedia
{
	public function __construct(
		private readonly Config $config,
		private readonly Database $db,
	) {}

	/** @param list<string> $size */
	public function __invoke(
		Io $io,
		#[Opt('A size to generate, repeatable; every size without it', value: 'name')]
		array $size = [],
	): int {
		$sizes = $this->config->media->sizes;
		$names = $size === [] ? $sizes->names() : $size;

		foreach ($names as $name) {
			if (!$sizes->has($name)) {
				$io->error('There is no size <strong>%s</strong>.', $name);

				return 1;
			}
		}

		$renditions = new Renditions($this->config);
		$written = 0;
		$failed = 0;

		foreach ($this->db->assets->all()->all() as $row) {
			$asset = Asset::fromRow($row, $this->config);

			if (!$asset->resizable()) {
				continue;
			}

			foreach ($names as $name) {
				$spec = $sizes->get($name);

				try {
					$renditions->render($asset, $asset->renditionFile($spec), $spec);
					$written++;
				} catch (RuntimeException $e) {
					$failed++;
					$io->error('%s (%s, %s): %s', $asset->filename, $asset->uid, $name, $e->getMessage());
				}
			}
		}

		$io->line('%d rendition(s) in place, %d failed.', $written, $failed);

		return $failed === 0 ? 0 : 1;
	}
}

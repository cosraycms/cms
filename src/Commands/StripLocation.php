<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Quma\Database;
use Cosray\Assets\Location;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Storage\Storage;

#[Command(
	'media:strip-location',
	'Finds location data in stored originals; --apply removes it for good',
	group: 'Media',
)]
final class StripLocation
{
	public function __construct(
		private readonly Config $config,
		private readonly Database $db,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Rewrite the files, which cannot be undone without a backup')]
		bool $apply = false,
	): int {
		$storage = new Storage($this->config);
		$found = 0;
		$checked = 0;

		foreach ($this->db->assets->all()->all() as $row) {
			if ($row['disk'] !== 'local') {
				continue;
			}

			try {
				$path = $storage->path((string) $row['key']);
			} catch (RuntimeException) {
				continue;
			}

			$checked++;
			$contents = (string) file_get_contents($path);
			$clean = Location::removed($contents, (string) $row['mime']);

			if ($clean === $contents) {
				continue;
			}

			$found++;
			$io->line('%s  %s', (string) $row['uid'], (string) $row['filename']);

			if ($apply) {
				$this->replace($path, $clean);
				$this->db->assets->updateHash(['uid' => $row['uid'], 'hash' => hash('sha256', $clean)])->run();
			}
		}

		$io->line(
			$apply ? 'Removed location data from %d of %d stored files.' : '%d of %d stored files hold location data.',
			$found,
			$checked,
		);

		if (!$apply && $found > 0) {
			$io->line('Back them up, then run this again with <strong>--apply</strong>.');
		}

		return 0;
	}

	private function replace(string $path, string $contents): void
	{
		$tmp = "{$path}.tmp-" . bin2hex(random_bytes(6));
		file_put_contents($tmp, $contents);
		chmod($tmp, fileperms($path) & 0o777);
		rename($tmp, $path);
	}
}

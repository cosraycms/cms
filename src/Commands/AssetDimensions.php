<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Quma\Database;
use Cosray\Assets\Dimensions;
use Cosray\Config;
use Cosray\Storage\Storage;

/** Corrects JPEGs cataloged before their EXIF rotation counted for their dimensions. */
#[Command('db:asset-dimensions', 'Corrects the stored dimensions of rotated JPEGs', group: 'Database')]
class AssetDimensions
{
	public function __construct(
		private readonly Database $db,
		private readonly Config $config,
	) {}

	public function __invoke(Io $io): int
	{
		$storage = new Storage($this->config);
		$checked = 0;
		$corrected = 0;
		$missing = 0;

		foreach ($this->db->assets->jpegs()->all() as $row) {
			$checked++;
			$key = (string) $row['key'];

			if (!$storage->exists($key)) {
				$missing++;

				continue;
			}

			$dimensions = Dimensions::of($storage->read($key));

			if ($dimensions === null || [$row['width'], $row['height']] === $dimensions) {
				continue;
			}

			$this->db->assets->updateDimensions([
				'uid' => $row['uid'],
				'width' => $dimensions[0],
				'height' => $dimensions[1],
			])->run();
			$corrected++;
		}

		echo
			"Asset dimensions: {$corrected} of {$checked} JPEGs corrected"
				. ($missing > 0 ? ", {$missing} files missing" : '')
				. "\n";

		return 0;
	}
}

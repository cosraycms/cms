<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000049_AssetDimensions;

use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Assets\Dimensions;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Storage\Storage;

/**
 * Corrects JPEGs cataloged with their raw dimensions, which a camera's EXIF
 * rotation turns around in every rendition. A file that cannot be read is
 * reported and keeps its values.
 */
final class Migration implements Contract\Migration
{
	private readonly Storage $storage;

	public function __construct(Config $config)
	{
		$this->storage = new Storage($config);
	}

	public function run(Environment $env): void
	{
		$rows = $env->db->execute($this->sql(
			$env,
			"SELECT uid, key, width, height FROM /*:cms.prefix:*/assets WHERE mime = 'image/jpeg'",
		))->all();
		$corrected = 0;
		$missing = 0;

		foreach ($rows as $row) {
			try {
				$dimensions = Dimensions::ofFile($this->storage->path((string) $row['key']));
			} catch (RuntimeException) {
				$missing++;

				continue;
			}

			if ($dimensions === null || [$row['width'], $row['height']] === $dimensions) {
				continue;
			}

			$env->db->execute(
				$this->sql($env, 'UPDATE /*:cms.prefix:*/assets SET width = :width, height = :height WHERE uid = :uid'),
				['uid' => $row['uid'], 'width' => $dimensions[0], 'height' => $dimensions[1]],
			)->run();
			$corrected++;
		}

		echo "  Asset dimensions: {$corrected} of " . count($rows) . ' JPEGs corrected'
			. ($missing > 0 ? ", {$missing} files missing and left as they are" : '')
			. "\n";
	}

	private function sql(Environment $env, string $sql): string
	{
		return $env->conn->config->placeholders?->compileSql($sql, __FILE__) ?? $sql;
	}
}

return Migration::class;

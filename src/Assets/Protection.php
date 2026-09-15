<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Celema\Quma\Database;
use Cosray\Access;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Storage\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

final class Protection
{
	public function __construct(
		private readonly Config $config,
		private readonly Database $db,
	) {}

	public function protect(string $uid, string $permission): Asset
	{
		Access::validatePermission($this->config, $permission);
		if ($permission === 'everyone') {
			throw new RuntimeException('Protection cannot publish an asset');
		}

		$owns = !$this->db->getConn()->inTransaction();
		if ($owns) {
			$this->db->begin();
		}

		try {
			$row = $this->db->assets->lock(['uid' => $uid])->first();
			if ($row === null) {
				throw new RuntimeException('Asset not found: ' . $uid);
			}

			$this->db->assets->lockHash(['hash' => (string) ($row['hash'] ?? $uid)])->run();
			$private = new Storage($this->config, 'private');
			$key = (string) $row['key'];
			if ($row['disk'] === 'local') {
				$public = new Storage($this->config);
				if ($public->exists($key)) {
					$private->write($key, $public->read($key));
				} elseif (!$private->exists($key)) {
					throw new RuntimeException('Asset original is missing');
				}

				// Remove public bytes before changing the row. A crash or rollback
				// leaves an unavailable public URL, never an unprotected private file;
				// the private copy lets the next invocation finish the operation.
				$public->deleteDirectory(dirname($key));
				new Filesystem(new LocalFilesystemAdapter(
					$this->config->path->public . '/' . trim($this->config->path->cache, '/'),
				))->deleteDirectory(dirname($key));
			}

			$this->db->assets->protect(['uid' => $uid, 'permission' => $permission])->run();
			if ($owns) {
				$this->db->commit();
			}

			return Asset::fromRow([...$row, 'disk' => 'private', 'permission' => $permission], $this->config);
		} catch (Throwable $error) {
			if ($owns && $this->db->getConn()->inTransaction()) {
				$this->db->rollback();
			}
			throw $error;
		}
	}
}

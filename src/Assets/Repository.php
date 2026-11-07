<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Celema\Quma\Database;
use Cosray\Config;

/**
 * Asset catalog access with a per-instance cache. One instance lives on
 * the request Context, so every value object of a request shares one
 * batch-loaded uid map instead of issuing per-item queries.
 */
class Repository
{
	/** @var array<string, ?Asset> */
	protected array $cache = [];

	public function __construct(
		protected readonly Database $db,
		protected readonly Config $config,
	) {}

	/** @param list<string> $uids */
	public function preload(array $uids): void
	{
		$missing = [];

		foreach ($uids as $uid) {
			if (array_key_exists($uid, $this->cache)) {
				continue;
			}

			$missing[] = $uid;
		}

		if ($missing === []) {
			return;
		}

		foreach ($this->fetch($missing) as $row) {
			$this->add($this->fromRow($row));
		}

		// Remember misses so unknown uids are not refetched per item.
		foreach ($missing as $uid) {
			$this->cache[$uid] ??= null;
		}
	}

	public function get(string $uid): ?Asset
	{
		if (!array_key_exists($uid, $this->cache)) {
			$this->preload([$uid]);
		}

		return $this->cache[$uid];
	}

	/** Seed the cache, e.g. after an upload or in tests. */
	public function add(Asset $asset): void
	{
		$this->cache[$asset->uid] = $asset;
	}

	/**
	 * @param list<string> $uids
	 * @return list<array<mixed>>
	 */
	protected function fetch(array $uids): array
	{
		return $this->db->assets->byUids(['uids' => $uids])->all();
	}

	/** @param array<mixed> $row */
	protected function fromRow(array $row): Asset
	{
		return Asset::fromRow($row, $this->config);
	}
}

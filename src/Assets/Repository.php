<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Celemas\Quma\Database;
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

	/**
	 * Collect asset uids referenced by media items in a content array.
	 * Media items are exactly `{uid}` or `{uid, meta}`; arrays with more
	 * keys (blocks, entries) are descended into instead.
	 *
	 * @return list<string>
	 */
	public static function collectUids(array $content): array
	{
		$uids = [];
		$walk = static function (array $data) use (&$walk, &$uids): void {
			if (
				is_string($data['uid'] ?? null)
				&& $data['uid'] !== ''
				&& array_diff(array_keys($data), ['uid', 'meta']) === []
			) {
				$uids[$data['uid']] = true;

				return;
			}

			foreach ($data as $value) {
				if (!is_array($value)) {
					continue;
				}

				$walk($value);
			}
		};
		$walk($content);

		return array_keys($uids);
	}

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

	/** @param list<string> $uids */
	protected function fetch(array $uids): array
	{
		return $this->db->assets->byUids(['uids' => $uids])->all();
	}

	protected function fromRow(array $row): Asset
	{
		return Asset::fromRow($row, $this->config);
	}
}

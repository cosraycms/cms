<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Celema\Quma\Database;
use Cosray\Config;
use Cosray\References\Usage;
use Cosray\Storage\Storage;
use DateTimeImmutable;
use PDOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The asset catalog as the panel browses and prunes it: paged listings
 * filtered by kind, filename and upload date, and the deletion of assets
 * nothing references. The media screen and the JSON endpoints share it.
 */
final class Library
{
	/** The filter vocabulary, which splits the catalog kind `file` in two. */
	public const array KINDS = ['image', 'video', 'audio', 'document'];

	/** Upload date ranges the media screen offers, as URL tokens. */
	public const array RANGES = ['7d', '30d', 'year'];

	public const int LIMIT = 60;

	public function __construct(
		private readonly Database $db,
		private readonly Config $config,
	) {}

	/**
	 * One page of the catalog, newest first. `$kinds` takes filter kinds;
	 * none, or all four, list everything. `$q` matches the filename, `$since`
	 * cuts on the created timestamp. The counts report per-kind totals that
	 * honor `$q` and `$since` but not `$kinds`, so a filter can show what
	 * selecting each kind would yield.
	 *
	 * @param list<string> $kinds
	 * @param list<string>|null $uids
	 */
	public function page(
		array $kinds = [],
		string $q = '',
		?string $since = null,
		int $page = 1,
		?array $uids = null,
	): LibraryPage {
		$page = max(1, $page);
		$args = ['limit' => self::LIMIT + 1, 'offset' => ($page - 1) * self::LIMIT];
		// The null seed keeps the args named and non-empty when no filter
		// applies — Quma templates refuse empty argument lists — and
		// isset() in the template still skips the clause.
		$countArgs = ['q' => null];
		$kinds = self::filterKinds($kinds);

		if ($kinds !== []) {
			$args['kinds'] = json_encode($kinds);
		}

		$q = trim($q);

		if ($q !== '') {
			$args['q'] = '%' . addcslashes($q, '%_\\') . '%';
			$countArgs['q'] = $args['q'];
		}

		if ($since !== null) {
			$args['since'] = $since;
			$countArgs['since'] = $since;
		}

		if ($uids !== null) {
			$args['uids'] = $uids;
		}

		$rows = $this->db->assets->list($args)->all();
		$counts = array_fill_keys(self::KINDS, 0);

		foreach ($this->db->assets->counts($countArgs)->all() as $row) {
			$counts[(string) $row['kind']] = (int) $row['total'];
		}

		return new LibraryPage(
			assets: array_map(
				fn(array $row): Asset => Asset::fromRow($row, $this->config),
				array_slice($rows, 0, self::LIMIT),
			),
			page: $page,
			more: count($rows) > self::LIMIT,
			// 0 when paging past the end: the window count needs a row to ride on.
			total: $rows === [] ? 0 : (int) $rows[0]['total'],
			counts: $counts,
		);
	}

	/**
	 * An asset as a picker hands it to the browser: what a control needs to
	 * show and store it.
	 *
	 * @return array{uid: string, filename: string, url: string, thumbUrl: string, previewUrl: string, kind: string, mime: ?string, bytes: ?int, width: ?int, height: ?int}
	 */
	public static function item(Asset $asset): array
	{
		return [
			'uid' => $asset->uid,
			'filename' => $asset->filename,
			'url' => $asset->path(),
			'thumbUrl' => $asset->resizable() ? $asset->sizePath('thumb') : $asset->path(),
			'previewUrl' => $asset->resizable() ? $asset->sizePath('preview') : $asset->path(),
			'kind' => $asset->kind,
			'mime' => $asset->mime,
			'bytes' => $asset->bytes,
			'width' => $asset->width,
			'height' => $asset->height,
		];
	}

	/**
	 * Hard delete, unreferenced only. Returns null for an unknown asset, the
	 * owners that still reference it when it is in use, and an empty list
	 * once it is gone. The RESTRICT foreign key on `asset_references` is the
	 * backstop against references appearing mid-request. The catalog row
	 * goes first: a leftover file is a harmless orphan, a dangling row is not.
	 *
	 * @return list<array{ownerType: string, ownerUid: string, title: string, nodeType: ?string, published: ?bool}>|null
	 */
	public function delete(string $uid): ?array
	{
		$row = $this->db->assets->byUid(['uid' => $uid])->first();

		if (!$row) {
			return null;
		}

		$usage = new Usage($this->db);
		$owners = $usage->forAsset($uid);

		if ($owners !== []) {
			return $owners;
		}

		try {
			$this->db->assets->delete(['uid' => $uid])->run();
		} catch (PDOException $e) {
			// RESTRICT violations report SQLSTATE 23001; plain FK
			// violations 23503.
			if (in_array((string) $e->getCode(), ['23001', '23503'], true)) {
				return $usage->forAsset($uid);
			}

			throw $e;
		}

		if ($row['disk'] === 'local') {
			new Storage($this->config)->deleteDirectory(dirname((string) $row['key']));
			$this->purgeRenditions((string) $row['key']);
		}

		return [];
	}

	/**
	 * The valid filter kinds among the given ones, from a list or a
	 * comma-separated string. All four mean everything, so they come back
	 * as none, which keeps the query plan flat.
	 *
	 * @param string|list<string> $kinds
	 * @return list<string>
	 */
	public static function filterKinds(string|array $kinds): array
	{
		$kinds = is_string($kinds) ? explode(',', $kinds) : $kinds;
		$requested = array_values(array_intersect(self::KINDS, array_map(trim(...), $kinds)));

		return count($requested) === count(self::KINDS) ? [] : $requested;
	}

	/** A created-timestamp cutoff, normalized; invalid input means none. */
	public static function since(mixed $value): ?string
	{
		if (!is_string($value) || trim($value) === '') {
			return null;
		}

		$time = strtotime($value);

		return $time === false ? null : date(DATE_ATOM, $time);
	}

	/**
	 * The cutoff an upload date range stands for right now. A link keeps the
	 * token, not the timestamp, so a shared "last 7 days" stays that.
	 */
	public static function rangeSince(string $range, ?DateTimeImmutable $now = null): ?string
	{
		$now ??= new DateTimeImmutable();

		$since = match ($range) {
			'7d' => $now->modify('-7 days'),
			'30d' => $now->modify('-30 days'),
			'year' => $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0),
			default => null,
		};

		return $since?->format(DATE_ATOM);
	}

	/** Removes the rendition cache directory `{cache}/{shard}/{uid}/`. */
	private function purgeRenditions(string $key): void
	{
		$root = rtrim($this->config->path->public, '\\/') . '/' . trim($this->config->path->cache, '/');
		$dir = $root . '/' . dirname($key);

		if (!is_dir($dir) || !str_starts_with((string) realpath($dir), (string) realpath($root))) {
			return;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($dir);
	}
}

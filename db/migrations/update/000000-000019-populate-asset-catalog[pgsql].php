<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000019_PopulateAssetCatalog;

use Celemas\Quma\Contract;
use Celemas\Quma\Environment;
use Cosray\Config;
use Cosray\Migration\NodeContentNormalizer;
use Cosray\Storage\Storage;
use Cosray\Uid;
use RuntimeException;

/**
 * Fills the asset catalog from the legacy owner-scoped media files,
 * rewrites every `{file}` media item to `{uid}` (nodes, drafts, both
 * history tables, menu items), moves the files into the sharded pool
 * layout and dumps a `legacy path → uid` mapping JSON.
 *
 * Every step is idempotent so an interrupted run can be repeated: the
 * mapping file doubles as resume state and is written before any
 * database row, already-rewritten items pass through unchanged, and
 * moves skip sources that are already gone.
 */
final class Migration implements Contract\Migration
{
	private const string MAP_FILE = 'asset-legacy-map.json';

	private const array KIND_BY_EXT = [
		'gif' => 'image',
		'jpeg' => 'image',
		'jpg' => 'image',
		'jfif' => 'image',
		'png' => 'image',
		'webp' => 'image',
		'svg' => 'image',
		'mp4' => 'video',
		'ogg' => 'video',
	];

	/** @var array<string, string> legacy path → asset uid */
	private array $map = [];

	private readonly Uid $uid;
	private readonly Storage $storage;

	public function __construct(
		private readonly Config $config,
	) {
		$this->uid = new Uid($config->uid->alphabet, $config->uid->length);
		$this->storage = new Storage($config);
	}

	public function run(Environment $env): void
	{
		$this->loadMap();
		$this->mintUids($this->collectPaths($env));
		// The map is the resume state; persist before touching any row.
		$this->writeMap();
		$this->insertRows($env);
		$this->disableContentTriggers($env);

		try {
			$this->rewriteContent($env);
			$this->rewriteHistory($env);
			$this->rewriteMenus($env);
		} finally {
			$this->enableContentTriggers($env);
		}

		$this->moveFiles();
		$this->cleanup();
	}

	// ------------------------------------------------------- catalog

	/** @return list<string> */
	private function collectPaths(Environment $env): array
	{
		$paths = [];

		foreach (['node', 'menu'] as $subtree) {
			foreach ($this->scanTree($this->assetBase() . '/' . $subtree) as $relative) {
				$paths[] = "{$subtree}/{$relative}";
			}
		}

		$rows = $env->db->execute($this->sql($env, '
			SELECT n.uid, n.content::text AS content
			FROM /*:cms.prefix:*/nodes n
			UNION ALL
			SELECT n.uid, d.content::text AS content
			FROM /*:cms.prefix:*/drafts d
			INNER JOIN /*:cms.prefix:*/nodes n USING (node)
		'))->all();

		foreach ($rows as $row) {
			$content = json_decode((string) $row['content'], true);

			if (!is_array($content)) {
				continue;
			}

			foreach ($this->collectFileRefs($content) as $file) {
				$paths[] = "node/{$row['uid']}/{$file}";
			}
		}

		$items = $env->db->execute($this->sql($env, '
			SELECT menu, data::text AS data FROM /*:cms.prefix:*/menu_items
		'))->all();
		// On a resumed run menu images already hold minted asset uids.
		$minted = array_flip($this->map);

		foreach ($items as $item) {
			$data = json_decode((string) $item['data'], true);
			$image = is_array($data) ? $data['image'] ?? null : null;

			if (is_string($image) && $image !== '' && !isset($minted[$image])) {
				$paths[] = "menu/{$item['menu']}/{$image}";
			}
		}

		return array_values(array_unique($paths));
	}

	/** @return list<string> relative paths below $dir */
	private function scanTree(string $dir): array
	{
		if (!is_dir($dir)) {
			return [];
		}

		$result = [];
		$owners = scandir($dir) ?: [];

		foreach ($owners as $owner) {
			if ($owner === '.' || $owner === '..') {
				continue;
			}

			$ownerDir = "{$dir}/{$owner}";

			if (!is_dir($ownerDir)) {
				continue;
			}

			foreach ($this->scanFiles($ownerDir) as $relative) {
				$result[] = "{$owner}/{$relative}";
			}
		}

		return $result;
	}

	/** @return list<string> */
	private function scanFiles(string $dir, string $prefix = ''): array
	{
		$result = [];

		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = "{$dir}/{$entry}";

			if (is_dir($path) && !is_link($path)) {
				foreach ($this->scanFiles($path, "{$prefix}{$entry}/") as $nested) {
					$result[] = $nested;
				}
			} elseif (is_file($path)) {
				$result[] = "{$prefix}{$entry}";
			}
		}

		return $result;
	}

	/**
	 * Media items are `{file}` or `{file, meta}` after migration 017;
	 * anything with more keys (blocks, entries) is descended into.
	 *
	 * @return list<string>
	 */
	private function collectFileRefs(array $data): array
	{
		$files = [];
		$walk = static function (array $data) use (&$walk, &$files): void {
			if (
				is_string($data['file'] ?? null)
				&& $data['file'] !== ''
				&& array_diff(array_keys($data), ['file', 'meta']) === []
			) {
				$files[$data['file']] = true;

				return;
			}

			foreach ($data as $value) {
				if (!is_array($value)) {
					continue;
				}

				$walk($value);
			}
		};
		$walk($data);

		return array_keys($files);
	}

	/** @param list<string> $paths */
	private function mintUids(array $paths): void
	{
		foreach ($paths as $path) {
			$this->map[$path] ??= $this->uid->generate();
		}
	}

	private function insertRows(Environment $env): void
	{
		$creator = $env->db->execute($this->sql($env, '
			SELECT min(usr) AS usr FROM /*:cms.prefix:*/users
		'))->one()['usr'] ?? null;

		if ($creator === null) {
			throw new RuntimeException('No user available as asset creator');
		}

		foreach ($this->map as $path => $uid) {
			$exists = $env->db->execute($this->sql($env, '
				SELECT 1 FROM /*:cms.prefix:*/assets WHERE uid = :uid
			'), ['uid' => $uid])->first();

			if ($exists) {
				continue;
			}

			$env->db->execute(
				$this->sql($env, '
				INSERT INTO /*:cms.prefix:*/assets
					(uid, disk, key, filename, mime, bytes, width, height, kind, hash, meta, creator)
				VALUES
					(:uid, :disk, :key, :filename, :mime, :bytes, :width, :height, :kind, :hash, \'{}\', :creator)
			'),
				$this->rowFor($path, $uid) + ['creator' => (int) $creator],
			)->run();
		}
	}

	/** @return array<string, mixed> */
	private function rowFor(string $path, string $uid): array
	{
		$filename = basename($path);
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$file = $this->assetBase() . '/' . $path;
		$row = [
			'uid' => $uid,
			'disk' => $this->storage->disk,
			'key' => Storage::key($uid, $ext),
			'filename' => $filename,
			'mime' => null,
			'bytes' => null,
			'width' => null,
			'height' => null,
			'kind' => self::KIND_BY_EXT[$ext] ?? 'file',
			'hash' => null,
		];

		// Referenced-but-missing files get a bare row: serving keeps
		// returning 404 exactly as before, but the content reference
		// survives the shape change.
		if (!is_file($file)) {
			return $row;
		}

		$contents = file_get_contents($file);

		if ($contents === false) {
			throw new RuntimeException("Could not read asset file '{$file}'");
		}

		$row['bytes'] = strlen($contents);
		$row['hash'] = hash('sha256', $contents);
		$mime = new \finfo(FILEINFO_MIME_TYPE)->buffer($contents);
		$row['mime'] = $mime === false ? null : $mime;

		if (is_string($mime)) {
			if (str_starts_with($mime, 'image/')) {
				$row['kind'] = 'image';
			} elseif (str_starts_with($mime, 'video/')) {
				$row['kind'] = 'video';
			}
		}

		if ($row['kind'] === 'image') {
			set_error_handler(static fn(): bool => true);

			try {
				$info = getimagesizefromstring($contents);
			} finally {
				restore_error_handler();
			}

			if ($info !== false) {
				$row['width'] = $info[0];
				$row['height'] = $info[1];
			}
		}

		return $row;
	}

	// ------------------------------------------------- content rewrite

	private function rewriteContent(Environment $env): void
	{
		$owner = '';
		$normalizer = new NodeContentNormalizer(
			$this->uid,
			mediaItem: function (array $item) use (&$owner): ?array {
				return $this->convertItem($item, "node/{$owner}");
			},
		);

		$tables = [
			[
				'sql' => '
				SELECT n.node, n.uid, n.content::text AS content
				FROM /*:cms.prefix:*/nodes n
			',
				'update' => 'UPDATE /*:cms.prefix:*/nodes SET content = :content::jsonb WHERE node = :node',
			],
			[
				'sql' => '
				SELECT d.node, n.uid, d.content::text AS content
				FROM /*:cms.prefix:*/drafts d
				INNER JOIN /*:cms.prefix:*/nodes n USING (node)
			',
				'update' => 'UPDATE /*:cms.prefix:*/drafts SET content = :content::jsonb WHERE node = :node',
			],
		];

		foreach ($tables as $table) {
			$rows = $env->db->execute($this->sql($env, $table['sql']))->all();

			foreach ($rows as $row) {
				$content = json_decode((string) $row['content'], true);

				if (!is_array($content)) {
					continue;
				}

				$owner = (string) $row['uid'];
				$encoded = json_encode($normalizer->normalize($content), JSON_THROW_ON_ERROR);

				if ($encoded === (string) $row['content']) {
					continue;
				}

				$env->db->execute($this->sql($env, $table['update']), [
					'node' => (int) $row['node'],
					'content' => $encoded,
				])->run();
			}
		}
	}

	/** @param array<string, mixed> $item */
	private function convertItem(array $item, string $ownerPath): ?array
	{
		// Already migrated (resumed run).
		if (isset($item['uid']) && !isset($item['file'])) {
			return $item;
		}

		$file = $item['file'] ?? null;

		// Broken placeholders without a filename cannot reference an
		// asset; they would fail the new shape validation, so drop them.
		if (!is_string($file) || $file === '') {
			return null;
		}

		$uid = $this->map["{$ownerPath}/{$file}"] ?? null;

		if ($uid === null) {
			return null;
		}

		$result = ['uid' => $uid];
		$meta = $this->pruneMeta($item['meta'] ?? null);

		if ($meta !== []) {
			$result['meta'] = $meta;
		}

		return $result;
	}

	/**
	 * Keep only meta keys that carry an actual value in some locale.
	 * Empty per-use meta would shadow the asset's catalog defaults.
	 */
	private function pruneMeta(mixed $meta): array
	{
		if (!is_array($meta)) {
			return [];
		}

		$result = [];

		foreach ($meta as $key => $value) {
			if (!is_array($value)) {
				if ($value !== null && $value !== '') {
					$result[$key] = $value;
				}

				continue;
			}

			foreach ($value as $localized) {
				if ($localized === null || $localized === '' || $localized === []) {
					continue;
				}

				$result[$key] = $value;

				break;
			}
		}

		return $result;
	}

	/**
	 * History snapshots keep their historical shape; only `{file: F, …}`
	 * dicts whose F resolves in the owner's legacy map become `{uid, …}`.
	 * Migration 017 deliberately skipped history, so a full normalization
	 * here would reshape old snapshots as a side effect.
	 */
	private function rewriteHistory(Environment $env): void
	{
		$tables = [
			['sql' => '
				SELECT h.node, h.changed, n.uid, h.content::text AS content
				FROM /*:cms.prefix:*/nodes_history h
				INNER JOIN /*:cms.prefix:*/nodes n USING (node)
			', 'update' => '
				UPDATE /*:cms.prefix:*/nodes_history SET content = :content::jsonb
				WHERE node = :node AND changed = :changed
			'],
			['sql' => '
				SELECT h.node, h.changed, n.uid, h.content::text AS content
				FROM /*:cms.prefix:*/drafts_history h
				INNER JOIN /*:cms.prefix:*/nodes n USING (node)
			', 'update' => '
				UPDATE /*:cms.prefix:*/drafts_history SET content = :content::jsonb
				WHERE node = :node AND changed = :changed
			'],
		];

		foreach ($tables as $table) {
			$rows = $env->db->execute($this->sql($env, $table['sql']))->all();

			foreach ($rows as $row) {
				$content = json_decode((string) $row['content'], true);

				if (!is_array($content)) {
					continue;
				}

				$changed = false;
				$content = $this->walkRewrite($content, "node/{$row['uid']}", $changed);

				if (!$changed) {
					continue;
				}

				$env->db->execute($this->sql($env, $table['update']), [
					'node' => (int) $row['node'],
					'changed' => (string) $row['changed'],
					'content' => json_encode($content, JSON_THROW_ON_ERROR),
				])->run();
			}
		}
	}

	private function walkRewrite(array $data, string $ownerPath, bool &$changed): array
	{
		foreach ($data as $key => $value) {
			if (!is_array($value)) {
				continue;
			}

			$file = $value['file'] ?? null;

			if (is_string($file) && $file !== '') {
				$uid = $this->map["{$ownerPath}/{$file}"] ?? null;

				if ($uid !== null) {
					unset($value['file']);
					$data[$key] = ['uid' => $uid] + $value;
					$changed = true;

					continue;
				}
			}

			$data[$key] = $this->walkRewrite($value, $ownerPath, $changed);
		}

		return $data;
	}

	private function rewriteMenus(Environment $env): void
	{
		$rows = $env->db->execute($this->sql($env, '
			SELECT item, menu, data::text AS data FROM /*:cms.prefix:*/menu_items
		'))->all();

		foreach ($rows as $row) {
			$data = json_decode((string) $row['data'], true);

			if (!is_array($data)) {
				continue;
			}

			$image = $data['image'] ?? null;

			if (!is_string($image) || $image === '') {
				continue;
			}

			$uid = $this->map["menu/{$row['menu']}/{$image}"] ?? null;

			if ($uid === null) {
				continue;
			}

			$data['image'] = $uid;
			$env->db->execute($this->sql($env, '
				UPDATE /*:cms.prefix:*/menu_items SET data = :data::jsonb WHERE item = :item
			'), [
				'item' => (string) $row['item'],
				'data' => json_encode($data, JSON_THROW_ON_ERROR),
			])->run();
		}
	}

	// ------------------------------------------------------ file move

	private function moveFiles(): void
	{
		foreach ($this->map as $path => $uid) {
			if (!$this->storage->exists($path)) {
				continue;
			}

			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			$key = Storage::key($uid, $ext);

			if ($this->storage->exists($key)) {
				// A previous run already copied it; drop the leftover.
				$this->storage->delete($path);

				continue;
			}

			$this->storage->move($path, $key);
		}
	}

	private function cleanup(): void
	{
		foreach (['node', 'menu'] as $subtree) {
			$this->removeEmptyDirs($this->assetBase() . '/' . $subtree);
			$this->deletePath($this->cacheBase() . '/' . $subtree);
		}
	}

	private function removeEmptyDirs(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = "{$dir}/{$entry}";

			if (is_dir($path) && !is_link($path)) {
				$this->removeEmptyDirs($path);
			}
		}

		$remaining = array_diff(scandir($dir) ?: [], ['.', '..']);

		if ($remaining === []) {
			rmdir($dir);
		}
	}

	private function deletePath(string $path): void
	{
		if (!file_exists($path) && !is_link($path)) {
			return;
		}

		if (is_file($path) || is_link($path)) {
			if (!unlink($path)) {
				throw new RuntimeException("Could not delete cache file '{$path}'");
			}

			return;
		}

		foreach (scandir($path) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$this->deletePath("{$path}/{$entry}");
		}

		if (!rmdir($path)) {
			throw new RuntimeException("Could not delete cache directory '{$path}'");
		}
	}

	// -------------------------------------------------------- mapping

	private function loadMap(): void
	{
		$file = $this->mapFile();

		if (!is_file($file)) {
			return;
		}

		$decoded = json_decode((string) file_get_contents($file), true);

		if (is_array($decoded)) {
			/** @var array<string, string> $decoded */
			$this->map = $decoded;
		}
	}

	private function writeMap(): void
	{
		$encoded = json_encode(
			$this->map,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
		);

		if (file_put_contents($this->mapFile(), $encoded . "\n") === false) {
			throw new RuntimeException("Could not write mapping file '{$this->mapFile()}'");
		}
	}

	private function mapFile(): string
	{
		return rtrim($this->config->path->root, '/') . '/' . self::MAP_FILE;
	}

	// --------------------------------------------------------- shared

	private function assetBase(): string
	{
		return rtrim($this->config->path->public, '/') . '/' . trim($this->config->path->assets, '/');
	}

	private function cacheBase(): string
	{
		return rtrim($this->config->path->public, '/') . '/' . trim($this->config->path->cache, '/');
	}

	private function disableContentTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
			ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
			ALTER TABLE /*:cms.prefix:*/drafts DISABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
			SQL))->run();
	}

	private function enableContentTriggers(Environment $env): void
	{
		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/drafts ENABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
			ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
			ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
			SQL))->run();
	}

	private function sql(Environment $env, string $sql): string
	{
		return $env->conn->applyPlaceholders($sql, __FILE__);
	}
}

return Migration::class;

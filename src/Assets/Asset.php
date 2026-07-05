<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Cosray\Config;

/** A row from the asset catalog. */
final class Asset
{
	private const array RESIZABLE = ['image/gif', 'image/jpeg', 'image/png', 'image/webp'];

	public function __construct(
		public readonly string $uid,
		public readonly string $disk,
		public readonly string $key,
		public readonly string $filename,
		public readonly string $kind,
		public readonly ?string $mime = null,
		public readonly ?int $bytes = null,
		public readonly ?int $width = null,
		public readonly ?int $height = null,
		public readonly array $meta = [],
		private readonly string $assetsBase = '/assets',
		private readonly string $cacheBase = '/cache',
	) {}

	public static function fromRow(array $row, Config $config): self
	{
		$meta = json_decode((string) ($row['meta'] ?? '{}'), true);
		$prefix = $config->path->prefix;

		return new self(
			uid: (string) $row['uid'],
			disk: (string) $row['disk'],
			key: (string) $row['key'],
			filename: (string) $row['filename'],
			kind: (string) $row['kind'],
			mime: isset($row['mime']) ? (string) $row['mime'] : null,
			bytes: isset($row['bytes']) ? (int) $row['bytes'] : null,
			width: isset($row['width']) ? (int) $row['width'] : null,
			height: isset($row['height']) ? (int) $row['height'] : null,
			meta: is_array($meta) ? $meta : [],
			assetsBase: $prefix . '/' . trim($config->path->assets, '/'),
			cacheBase: $prefix . '/' . trim($config->path->cache, '/'),
		);
	}

	/**
	 * Root-relative URL of the original. The URL equals the file's path
	 * below the public directory, so the web server serves it natively.
	 */
	public function path(): string
	{
		return "{$this->assetsBase}/" . $this->encode($this->key);
	}

	/**
	 * Root-relative URL of a named rendition:
	 * `{cache}/{shard}/{uid}/{stem}-{size}.{ext}`. Served natively once
	 * generated; misses fall through to the cache route.
	 */
	public function sizePath(string $size): string
	{
		$dir = dirname($this->key);
		$base = basename($this->key);
		$dot = strrpos($base, '.');
		$variant =
			$dot === false || $dot === 0
				? "{$base}-{$size}"
				: substr($base, 0, $dot) . "-{$size}" . substr($base, $dot);

		return "{$this->cacheBase}/" . $this->encode("{$dir}/{$variant}");
	}

	/** Whether the resize pipeline can process this asset. */
	public function resizable(): bool
	{
		return in_array($this->mime, self::RESIZABLE, true);
	}

	/** Locale map for a catalog meta key, or null when absent. */
	public function metaMap(string $key): ?array
	{
		$value = $this->meta[$key] ?? null;

		return is_array($value) ? $value : null;
	}

	private function encode(string $path): string
	{
		return implode('/', array_map(rawurlencode(...), explode('/', $path)));
	}
}

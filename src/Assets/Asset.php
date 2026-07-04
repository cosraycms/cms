<?php

declare(strict_types=1);

namespace Cosray\Assets;

/** A row from the asset catalog. */
final class Asset
{
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
		private readonly string $prefix = '',
	) {}

	/**
	 * Root-relative media URL `{prefix}/media/{type}/{uid}/{filename}`.
	 * The type selects the serving route (image enables resizing) and
	 * defaults to the asset's kind; the filename segment is cosmetic.
	 */
	public function mediaPath(?string $type = null): string
	{
		$type ??= $this->kind;

		return "{$this->prefix}/media/{$type}/{$this->uid}/" . rawurlencode($this->filename);
	}

	/** Locale map for a catalog meta key, or null when absent. */
	public function metaMap(string $key): ?array
	{
		$value = $this->meta[$key] ?? null;

		return is_array($value) ? $value : null;
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Panel;

final class CollectionUrls
{
	public function __construct(
		private readonly string $panelPath,
		private readonly string $slug,
		public readonly CollectionQuery $query,
	) {}

	public function path(): string
	{
		return $this->panelPath . '/collection/' . rawurlencode($this->slug);
	}

	/** @param array<string, mixed> $overrides */
	public function collection(array $overrides = []): string
	{
		return $this->url($this->path(), $this->query->listParams($overrides));
	}

	/** @param array<string, mixed> $overrides */
	public function back(array $overrides = []): string
	{
		return $this->url($this->path(), $this->query->editorParams($overrides));
	}

	public function edit(string $uid): string
	{
		$path = $this->path() . '/' . rawurlencode($uid);

		return $this->url($path, $this->query->editorParams());
	}

	public function create(string $type, ?string $parent = null): string
	{
		$overrides = [];

		if ($parent !== null) {
			$overrides['parent'] = $parent;
		}

		$path = $this->path() . '/create/' . rawurlencode($type);

		return $this->url($path, $this->query->editorParams($overrides));
	}

	/** @param array<string, mixed> $params */
	private function url(string $path, array $params): string
	{
		$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

		return $query === '' ? $path : $path . '?' . $query;
	}
}

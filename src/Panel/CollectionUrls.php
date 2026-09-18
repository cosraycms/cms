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

	public function expand(string $uid): string
	{
		$open = $this->query->open;

		if (!in_array($uid, $open, true)) {
			$open[] = $uid;
		}

		return $this->collection([
			'view' => 'tree',
			'open' => $open,
		]);
	}

	/** @param list<string> $descendants */
	public function collapse(string $uid, array $descendants = []): string
	{
		$closed = array_fill_keys(array_merge([$uid], $descendants), true);
		$open = array_values(array_filter(
			$this->query->open,
			static fn(string $openUid): bool => !isset($closed[$openUid]),
		));

		return $this->collection([
			'view' => 'tree',
			'open' => $open,
		]);
	}

	public function children(string $uid): string
	{
		return $this->collection([
			'parent' => $uid,
			'view' => '',
			'open' => '',
			'offset' => '',
		]);
	}

	public function showInTree(string $uid): string
	{
		$open = $this->query->open;

		if (!in_array($uid, $open, true)) {
			$open[] = $uid;
		}

		return $this->collection([
			'parent' => '',
			'view' => 'tree',
			'open' => $open,
			'offset' => '',
		]);
	}

	public function edit(string $uid): string
	{
		return new NodeUrls($this->panelPath, 'collection:' . $this->slug, $this->query)->edit($uid);
	}

	/**
	 * Bulk-action endpoint, carrying the full listing query (offset
	 * included) so the response can redirect back to the same view.
	 */
	public function bulk(string $action): string
	{
		$path = $this->path() . '/bulk/' . rawurlencode($action);

		return $this->url($path, $this->query->editorParams());
	}

	public function create(string $type, ?string $parent = null): string
	{
		return new NodeUrls($this->panelPath, 'collection:' . $this->slug, $this->query)
			->create($type, $parent ?? $this->query->parent);
	}

	/** @param array<string, mixed> $params */
	private function url(string $path, array $params): string
	{
		$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

		return $query === '' ? $path : $path . '?' . $query;
	}
}

<?php

declare(strict_types=1);

namespace Cosray\Panel;

final class NodeUrls
{
	public function __construct(
		private readonly string $panelPath,
		private readonly string $from = '',
		private readonly ?CollectionQuery $listing = null,
	) {}

	public function edit(string $uid): string
	{
		return $this->url($this->path($uid), $this->origin());
	}

	public function delete(string $uid): string
	{
		return $this->url($this->path($uid) . '/delete', $this->origin());
	}

	public function discard(string $uid): string
	{
		return $this->url($this->path($uid) . '/discard', $this->origin());
	}

	public function paths(string $uid): string
	{
		return $this->path($uid) . '/paths';
	}

	public function blocks(string $uid): string
	{
		return $this->path($uid) . '/blocks';
	}

	public function create(string $type, ?string $parent = null): string
	{
		return $this->url($this->path('create') . '/' . rawurlencode($type), [
			...$this->origin(),
			'parent' => $parent,
		]);
	}

	public function createPaths(string $type, ?string $parent = null): string
	{
		return $this->url($this->path('create') . '/' . rawurlencode($type) . '/paths', ['parent' => $parent]);
	}

	public function createBlocks(string $type, ?string $parent = null): string
	{
		return $this->url($this->path('create') . '/' . rawurlencode($type) . '/blocks', ['parent' => $parent]);
	}

	private function path(string $uid): string
	{
		return $this->panelPath . '/node/' . rawurlencode($uid);
	}

	private function origin(): array
	{
		return [
			'from' => $this->from,
			'list' => $this->listing?->editorParams(),
		];
	}

	private function url(string $path, array $params): string
	{
		$params = array_filter($params, static fn(mixed $value): bool => $value !== null && $value !== '');
		$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

		return $query === '' ? $path : $path . '?' . $query;
	}
}

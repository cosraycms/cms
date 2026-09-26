<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\Assets\Library;

/**
 * The media screen's state as its URL carries it: filter kinds, search,
 * upload date range, selected file and listing page. Every control of the
 * screen is a link or a GET form built from it, so a reload or a shared
 * link shows the same screen.
 */
final readonly class MediaScreen
{
	/** @param list<string> $kinds */
	public function __construct(
		public string $path,
		public array $kinds = [],
		public string $q = '',
		public string $range = '',
		public ?string $file = null,
		public int $page = 1,
	) {}

	/** @param array<array-key, mixed> $params */
	public static function fromParams(string $path, array $params): self
	{
		$range = is_string($params['range'] ?? null) ? $params['range'] : '';
		$file = is_string($params['file'] ?? null) ? trim($params['file']) : '';

		$kinds = $params['kind'] ?? '';

		return new self(
			path: $path,
			// The URL carries a comma list, the rail's checkboxes an array.
			kinds: Library::filterKinds(match (true) {
				is_string($kinds) => $kinds,
				is_array($kinds) => array_values(array_filter($kinds, is_string(...))),
				default => '',
			}),
			q: is_string($params['q'] ?? null) ? trim($params['q']) : '',
			range: in_array($range, Library::RANGES, true) ? $range : '',
			file: $file !== '' ? $file : null,
			page: max(1, (int) ($params['page'] ?? 1)),
		);
	}

	public function withFile(?string $file): self
	{
		return new self($this->path, $this->kinds, $this->q, $this->range, $file, $this->page);
	}

	public function filtered(): bool
	{
		return $this->kinds !== [] || $this->q !== '' || $this->range !== '';
	}

	/**
	 * The screen's URL with some parts changed: `kind` takes a list,
	 * `file` null to deselect, the rest strings; `page` falls back to the
	 * first unless given. `$below` addresses a path under the screen's, such
	 * as a file's save action, with the state still in the query.
	 *
	 * @param array{kind?: list<string>, q?: string, range?: string, file?: ?string, page?: int} $changes
	 */
	public function url(array $changes = [], string $below = ''): string
	{
		$path = $below === '' ? $this->path : "{$this->path}/{$below}";
		$query = http_build_query($this->query($changes), encoding_type: PHP_QUERY_RFC3986);

		return $query === '' ? $path : "{$path}?{$query}";
	}

	/**
	 * The non-empty query parameters, as hidden inputs of a GET form need them.
	 *
	 * @param array{kind?: list<string>, q?: string, range?: string, file?: ?string, page?: int} $changes
	 * @return array<string, string>
	 */
	public function query(array $changes = []): array
	{
		$params = [
			'kind' => implode(',', $changes['kind'] ?? $this->kinds),
			'q' => $changes['q'] ?? $this->q,
			'range' => $changes['range'] ?? $this->range,
			'file' => array_key_exists('file', $changes) ? (string) $changes['file'] : (string) $this->file,
			'page' => (string) ($changes['page'] ?? 1),
		];

		if ($params['page'] === '1') {
			$params['page'] = '';
		}

		return array_filter($params, static fn(string $value): bool => $value !== '');
	}
}

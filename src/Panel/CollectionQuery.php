<?php

declare(strict_types=1);

namespace Cosray\Panel;

final class CollectionQuery
{
	private const int DEFAULT_LIMIT = 50;

	/** @param list<string> $open */
	public function __construct(
		public readonly string $q = '',
		public readonly string $sort = '',
		public readonly string $dir = '',
		public readonly int $offset = 0,
		public readonly int $limit = self::DEFAULT_LIMIT,
		public readonly ?string $parent = null,
		public readonly string $view = 'list',
		public readonly array $open = [],
		public readonly string $defaultView = 'list',
	) {}

	/** @param array<string, mixed> $overrides */
	public function listParams(array $overrides = []): array
	{
		return $this->params([
			'q' => $this->q,
			'sort' => $this->sort,
			'dir' => $this->dir,
			'limit' => $this->limit,
			'parent' => $this->parent,
			'view' => $this->view,
			'open' => $this->open,
		], $overrides);
	}

	/** @param array<string, mixed> $overrides */
	public function editorParams(array $overrides = []): array
	{
		return $this->params([
			'q' => $this->q,
			'sort' => $this->sort,
			'dir' => $this->dir,
			'offset' => $this->offset,
			'limit' => $this->limit,
			'parent' => $this->parent,
			'view' => $this->view,
			'open' => $this->open,
		], $overrides);
	}

	/**
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function params(array $params, array $overrides): array
	{
		$params = array_merge($params, $overrides);

		if (array_key_exists('open', $params)) {
			$params['open'] = $this->openParam($params['open']);
		}

		$params = array_filter(
			$params,
			static fn(mixed $value): bool => $value !== null && $value !== '',
		);

		if (($params['view'] ?? null) === $this->defaultView) {
			unset($params['view']);
		}

		if ($this->isInt($params['offset'] ?? null, 0)) {
			unset($params['offset']);
		}

		if ($this->isInt($params['limit'] ?? null, self::DEFAULT_LIMIT)) {
			unset($params['limit']);
		}

		return $params;
	}

	private function openParam(mixed $value): string
	{
		if (is_string($value)) {
			return trim($value);
		}

		if (!is_array($value)) {
			return '';
		}

		$open = [];

		foreach ($value as $uid) {
			$uid = trim((string) $uid);

			if ($uid !== '' && !in_array($uid, $open, true)) {
				$open[] = $uid;
			}
		}

		return implode(',', $open);
	}

	private function isInt(mixed $value, int $expected): bool
	{
		if (is_int($value)) {
			return $value === $expected;
		}

		if (!is_string($value) || !preg_match('/^-?[0-9]+$/', $value)) {
			return false;
		}

		return (int) $value === $expected;
	}
}

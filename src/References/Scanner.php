<?php

declare(strict_types=1);

namespace Cosray\References;

use Cosray\Field;

/**
 * Collects every asset and node uid referenced by stored content. Each
 * field type names its own through `Field::references()`; the rows of
 * Blocks and Entries fields are recursed into.
 */
final class Scanner
{
	private static ?Field\Index $defaults = null;

	private readonly Field\Index $index;

	public function __construct(?Field\Index $index = null)
	{
		$this->index = $index ?? (self::$defaults ??= Field\Index::withDefaults());
	}

	/**
	 * @param mixed $content A map of field names to stored field data.
	 * @return array{assets: list<string>, nodes: list<string>}
	 */
	public function scan(mixed $content): array
	{
		$assets = [];
		$nodes = [];

		if (is_array($content)) {
			$this->fields($content, $assets, $nodes);
		}

		return ['assets' => $this->unique($assets), 'nodes' => $this->unique($nodes)];
	}

	/**
	 * The references of one field's stored data.
	 *
	 * @return array{assets: list<string>, nodes: list<string>}
	 */
	public function field(mixed $field): array
	{
		return $this->scan([$field]);
	}

	/**
	 * @param list<string> $assets
	 * @param list<string> $nodes
	 * @param array<mixed> $content
	 */
	private function fields(array $content, array &$assets, array &$nodes): void
	{
		foreach ($content as $field) {
			if (!is_array($field) || !is_string($field['type'] ?? null)) {
				continue;
			}

			$type = $this->index->resolve($field['type']);

			if ($type === null) {
				continue;
			}

			if (is_a($type, Field\Blocks::class, true) || is_a($type, Field\Entries::class, true)) {
				$this->rows($field['value'] ?? null, $assets, $nodes);

				continue;
			}

			$found = $type::references($field);
			$assets = [...$assets, ...$found['assets']];
			$nodes = [...$nodes, ...$found['nodes']];
		}
	}

	/**
	 * Typed rows carry their sub-fields under `fields`, per locale list.
	 *
	 * @param list<string> $assets
	 * @param list<string> $nodes
	 */
	private function rows(mixed $value, array &$assets, array &$nodes): void
	{
		foreach (is_array($value) ? $value : [] as $rows) {
			$this->list($rows, $assets, $nodes);
		}
	}

	/**
	 * A split of a Blocks field holds its rows under `blocks`.
	 *
	 * @param list<string> $assets
	 * @param list<string> $nodes
	 */
	private function list(mixed $rows, array &$assets, array &$nodes): void
	{
		foreach (is_array($rows) ? $rows : [] as $row) {
			if (!is_array($row)) {
				continue;
			}

			if (is_array($row['fields'] ?? null)) {
				$this->fields($row['fields'], $assets, $nodes);
			}

			if (is_array($row['blocks'] ?? null)) {
				$this->list($row['blocks'], $assets, $nodes);
			}
		}
	}

	/**
	 * @param list<string> $uids
	 * @return list<string>
	 */
	private function unique(array $uids): array
	{
		$uids = array_values(array_unique($uids));
		sort($uids);

		return $uids;
	}
}

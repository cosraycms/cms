<?php

declare(strict_types=1);

namespace Cosray\Richtext;

/**
 * Canonical form for richtext documents: fixed key order (type,
 * attrs, text, marks, content), sorted attribute keys, marks sorted
 * by type, attributes equal to their spec default omitted, empty
 * text runs dropped, adjacent text runs with identical marks merged.
 * Byte-stable storage keeps history diffs small and normalization
 * idempotent.
 */
final class Normalizer
{
	/** Canonical form of a document; null when it is empty. */
	public function normalize(mixed $doc): ?array
	{
		if (!is_array($doc) || ($doc['type'] ?? null) !== 'doc') {
			return null;
		}

		$content = $this->children($doc['content'] ?? []);

		if ($this->isEmpty($content)) {
			return null;
		}

		return ['type' => 'doc', 'content' => $content];
	}

	/** @return list<array<string, mixed>> */
	private function children(mixed $content): array
	{
		if (!is_array($content)) {
			return [];
		}

		$result = [];

		foreach ($content as $child) {
			if (!is_array($child) || !is_string($child['type'] ?? null)) {
				continue;
			}

			$node = $this->node($child);

			if ($node !== null) {
				$result[] = $node;
			}
		}

		return $this->mergeRuns($result);
	}

	/** @return null|array<string, mixed> */
	private function node(array $node): ?array
	{
		$type = $node['type'];

		if ($type === 'text') {
			return $this->text($node);
		}

		$result = ['type' => $type];
		$attrs = $this->attrs(
			is_array($node['attrs'] ?? null) ? $node['attrs'] : [],
			Spec::nodeDefaults($type),
		);

		if ($attrs !== []) {
			$result['attrs'] = $attrs;
		}

		if (!Spec::isLeaf($type)) {
			$content = $this->children($node['content'] ?? []);

			if ($content !== []) {
				$result['content'] = $content;
			}
		}

		return $result;
	}

	/** @return null|array<string, mixed> */
	private function text(array $node): ?array
	{
		$text = $node['text'] ?? null;

		if (!is_string($text) || $text === '') {
			return null;
		}

		$result = ['type' => 'text', 'text' => $text];
		$marks = $this->marks($node['marks'] ?? []);

		if ($marks !== []) {
			$result['marks'] = $marks;
		}

		return $result;
	}

	/** @return list<array<string, mixed>> */
	private function marks(mixed $marks): array
	{
		if (!is_array($marks)) {
			return [];
		}

		$result = [];

		foreach ($marks as $mark) {
			if (!is_array($mark) || !is_string($mark['type'] ?? null)) {
				continue;
			}

			$type = $mark['type'];
			$entry = ['type' => $type];
			$attrs = $this->attrs(
				is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [],
				Spec::markDefaults($type),
			);

			if ($attrs !== []) {
				$entry['attrs'] = $attrs;
			}

			$result[$type] = $entry;
		}

		ksort($result);

		return array_values($result);
	}

	/** @return array<string, mixed> */
	private function attrs(array $attrs, array $defaults): array
	{
		$result = [];

		foreach ($attrs as $key => $value) {
			$hasDefault = array_key_exists($key, $defaults);

			if ($hasDefault && $value === $defaults[$key]) {
				continue;
			}

			if (!$hasDefault && $value === null) {
				continue;
			}

			$result[$key] = $value;
		}

		ksort($result);

		return $result;
	}

	/**
	 * Merge adjacent text runs carrying identical mark sets.
	 *
	 * @param list<array<string, mixed>> $nodes
	 * @return list<array<string, mixed>>
	 */
	private function mergeRuns(array $nodes): array
	{
		$result = [];

		foreach ($nodes as $node) {
			$last = $result === [] ? null : $result[count($result) - 1];

			if (
				$last !== null
				&& $node['type'] === 'text'
				&& $last['type'] === 'text'
				&& ($node['marks'] ?? []) === ($last['marks'] ?? [])
			) {
				$result[count($result) - 1]['text'] = $last['text'] . $node['text'];

				continue;
			}

			$result[] = $node;
		}

		return $result;
	}

	/** @param list<array<string, mixed>> $content */
	private function isEmpty(array $content): bool
	{
		foreach ($content as $node) {
			if ($node['type'] !== 'paragraph' || ($node['content'] ?? []) !== []) {
				return false;
			}
		}

		return true;
	}
}

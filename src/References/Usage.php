<?php

declare(strict_types=1);

namespace Cosray\References;

use Celema\Quma\Database;

/**
 * Answers "who points at this?" from the reference indexes, enriched
 * for display: node owners carry their type handle, published state,
 * and best-effort title (the conventional `title` field; menu owners
 * fall back to the item's title map).
 */
final class Usage
{
	public function __construct(
		private readonly Database $db,
	) {}

	/** @return list<array{ownerType: string, ownerUid: string, title: string, nodeType: ?string, published: ?bool}> */
	public function forAsset(string $uid): array
	{
		return array_map(
			$this->row(...),
			$this->db->references->assetUsage(['uid' => $uid])->all(),
		);
	}

	/** @return list<array{ownerType: string, ownerUid: string, title: string, nodeType: ?string, published: ?bool}> */
	public function forNode(string $uid): array
	{
		return array_map(
			$this->row(...),
			$this->db->references->nodeUsage(['uid' => $uid])->all(),
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{ownerType: string, ownerUid: string, title: string, nodeType: ?string, published: ?bool}
	 */
	private function row(array $row): array
	{
		return [
			'ownerType' => (string) $row['ownerType'],
			'ownerUid' => (string) $row['ownerUid'],
			'title' => $this->title($row['title'] ?? null),
			'nodeType' => is_string($row['nodeType'] ?? null) ? $row['nodeType'] : null,
			'published' => is_bool($row['published'] ?? null) ? $row['published'] : null,
		];
	}

	private function title(mixed $value): string
	{
		$map = is_string($value) ? json_decode($value, true) : null;

		foreach (is_array($map) ? $map : [] as $title) {
			if (is_string($title) && $title !== '') {
				return $title;
			}
		}

		return '';
	}
}

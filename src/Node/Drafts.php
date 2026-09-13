<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Quma\Database;

/**
 * The working copies of published nodes: one row per node, content in
 * the shape of `nodes.content`, plus the drafted handle and URL paths.
 */
final class Drafts
{
	public function __construct(
		private readonly Database $db,
	) {}

	/**
	 * @return ?array{
	 *     node: int,
	 *     content: array<string, mixed>,
	 *     settings: array{handle?: ?string, paths?: array<string, string>},
	 *     created: string,
	 *     changed: string,
	 *     editor: int,
	 * }
	 */
	public function get(int $node): ?array
	{
		$row = $this->db->drafts->get(['node' => $node])->first();

		if (!$row) {
			return null;
		}

		$content = json_decode((string) $row['content'], true);
		$settings = json_decode((string) $row['settings'], true);

		return [
			'node' => (int) $row['node'],
			'content' => is_array($content) ? $content : [],
			'settings' => is_array($settings) ? $settings : [],
			'created' => (string) $row['created'],
			'changed' => (string) $row['changed'],
			'editor' => (int) $row['editor'],
		];
	}

	/**
	 * @param array<string, mixed> $content
	 * @param array{handle?: ?string, paths?: array<string, string>} $settings
	 */
	public function save(int $node, array $content, array $settings, int $editor): void
	{
		$this->db->drafts->upsert([
			'node' => $node,
			'content' => json_encode($content),
			'settings' => json_encode((object) $settings),
			'editor' => $editor,
		])->run();
	}

	public function delete(int $node): void
	{
		$this->db->drafts->delete(['node' => $node])->run();
	}

	/**
	 * @param list<string> $uids
	 * @return list<string>
	 */
	public function withDrafts(array $uids): array
	{
		if ($uids === []) {
			return [];
		}

		return array_map(
			static fn(array $row): string => (string) $row['uid'],
			$this->db->drafts->withDrafts(['uids' => json_encode(array_values($uids))])->all(),
		);
	}
}

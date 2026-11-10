<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Quma\Database;

/**
 * An editor's unsaved edits of a node, one row per node and user, kept for
 * restoring them. Content and settings have the shapes of a working copy
 * but are neither validated nor live.
 */
final class Autosaves
{
	/** Autosaves untouched for this long are pruned when a new one starts. */
	public const int DAYS = 14;

	public function __construct(
		private readonly Database $db,
	) {}

	/**
	 * @return ?array{
	 *     changed: string,
	 *     base: string,
	 *     content: array<string, mixed>,
	 *     settings: array<string, mixed>,
	 * }
	 */
	public function get(int $node, int $editor): ?array
	{
		$row = $this->db->autosaves->get(['node' => $node, 'editor' => $editor])->first();

		if (!$row) {
			return null;
		}

		$content = json_decode((string) $row['content'], true);
		$settings = json_decode((string) $row['settings'], true);

		return [
			'changed' => (string) $row['changed'],
			'base' => (string) $row['base'],
			'content' => is_array($content) ? $content : [],
			'settings' => is_array($settings) ? $settings : [],
		];
	}

	/**
	 * @param array<string, mixed> $content
	 * @param array<string, mixed> $settings
	 */
	public function save(int $node, int $editor, string $base, array $content, array $settings): void
	{
		$saved = $this->db
			->autosaves
			->upsert([
				'node' => $node,
				'editor' => $editor,
				'base' => $base,
				'content' => json_encode($content),
				'settings' => json_encode((object) $settings),
			])
			->first();

		if ($saved !== null && (bool) $saved['created']) {
			$this->db->autosaves->prune(['days' => self::DAYS])->run();
		}
	}

	public function delete(int $node, int $editor): void
	{
		$this->db->autosaves->delete(['node' => $node, 'editor' => $editor])->run();
	}
}

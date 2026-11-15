<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Quma\Database;

/**
 * An editor's unsaved edits of a node, one row per node and user, kept for
 * restoring them. Content and settings have the shapes of a working copy
 * but are neither validated nor live.
 */
final class Edits
{
	/** Edits untouched for this long are pruned when new ones start. */
	public const int DAYS = 14;

	public function __construct(
		private readonly Database $db,
	) {}

	/**
	 * @return ?array{
	 *     changed: string,
	 *     base: ?int,
	 *     content: array<string, mixed>,
	 *     settings: array<string, mixed>,
	 * }
	 */
	public function get(int $node, int $editor): ?array
	{
		$row = $this->db->edits->get(['node' => $node, 'editor' => $editor])->first();

		if (!$row) {
			return null;
		}

		$content = json_decode((string) $row['content'], true);
		$settings = json_decode((string) $row['settings'], true);

		return [
			'changed' => (string) $row['changed'],
			'base' => $row['base'] === null ? null : (int) $row['base'],
			'content' => is_array($content) ? $content : [],
			'settings' => is_array($settings) ? $settings : [],
		];
	}

	/**
	 * @param array<string, mixed> $content
	 * @param array<string, mixed> $settings
	 */
	public function save(int $node, int $editor, ?int $base, array $content, array $settings): void
	{
		$saved = $this->db
			->edits
			->upsert([
				'node' => $node,
				'editor' => $editor,
				'base' => $base,
				'content' => json_encode($content),
				'settings' => json_encode((object) $settings),
			])
			->first();

		if ($saved !== null && (bool) $saved['created']) {
			$this->db->edits->prune(['days' => self::DAYS])->run();
		}
	}

	public function delete(int $node, int $editor): void
	{
		$this->db->edits->delete(['node' => $node, 'editor' => $editor])->run();
	}

	/** Moves the user's edits from one revision onto another, when they started from the first. */
	public function rebase(int $node, int $editor, int $from, int $to): void
	{
		$this->db->edits->rebase(['node' => $node, 'editor' => $editor, 'from' => $from, 'to' => $to])->run();
	}
}

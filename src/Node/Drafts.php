<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Quma\Database;

/**
 * The working copies of nodes: one row per node, content in the shape of
 * `nodes.content`, plus the drafted handle and URL paths.
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
	 * Whether the working copy changed: saving the state it already holds
	 * writes nothing.
	 *
	 * @param array<string, mixed> $content
	 * @param array{handle?: ?string, paths?: array<string, string>} $settings
	 */
	public function save(int $node, array $content, array $settings, int $editor): bool
	{
		return $this->db
			->drafts
			->upsert([
				'node' => $node,
				'content' => json_encode($content),
				'settings' => json_encode((object) $settings),
				'editor' => $editor,
			])
			->first() !== null;
	}

	/**
	 * Whether a working copy would leave the live node as it is: the same
	 * content, handle and active URL paths, compared by Essence::of().
	 *
	 * @param array<string, mixed> $content
	 * @param array<string, string> $paths
	 */
	public function matchesLive(int $node, array $content, ?string $handle, array $paths): bool
	{
		$live = $this->db->drafts->live(['node' => $node])->one();
		$livePaths = json_decode((string) $live['paths'], true);
		$liveContent = json_decode((string) $live['content'], true);

		return (
			$live['handle'] === $handle
				&& Essence::of($livePaths) === Essence::of(array_map(trim(...), $paths))
				&& Essence::of($liveContent) === Essence::of($content)
		);
	}

	/**
	 * Deletes the working copy, if there is one, and says whether there was;
	 * its history keeps the last state and, when the caller says it was
	 * applied to the main version, records it as applied rather than
	 * discarded. Must run
	 * inside the caller's transaction: the reason travels as a
	 * transaction-local setting the history trigger reads.
	 */
	public function delete(int $node, bool $applied = false): bool
	{
		// Set either way: an earlier delete in the same transaction may have
		// left `applied` behind.
		$this->db->drafts->outcome(['outcome' => $applied ? 'applied' : 'discarded'])->run();

		return $this->db->drafts->delete(['node' => $node])->first() !== null;
	}
}

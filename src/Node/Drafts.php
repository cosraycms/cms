<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Quma\Database;

/**
 * The working copies of nodes: one row per node pointing at the revision
 * it holds, content in the shape of `nodes.content` plus the drafted
 * handle and URL paths.
 */
final class Drafts
{
	public function __construct(
		private readonly Database $db,
	) {}

	/**
	 * @return ?array{
	 *     node: int,
	 *     revision: int,
	 *     base: ?int,
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
			'revision' => (int) $row['revision'],
			'base' => $row['base'] === null ? null : (int) $row['base'],
			'content' => is_array($content) ? $content : [],
			'settings' => is_array($settings) ? $settings : [],
			'created' => (string) $row['created'],
			'changed' => (string) $row['changed'],
			'editor' => (int) $row['editor'],
		];
	}

	/**
	 * Saves the state as a new revision and points the working copy at it,
	 * unless the copy holds this state already. Returns the new revision.
	 * `$main` is the main revision the copy is up to date with; a later
	 * save keeps the first one's unless `$rebase` says the main version
	 * took the same change.
	 *
	 * @param array<string, mixed> $content
	 * @param array{handle?: ?string, paths?: array<string, string>} $settings
	 */
	public function save(
		int $node,
		array $content,
		array $settings,
		int $editor,
		?int $main,
		bool $rebase = false,
	): ?int {
		$current = $this->db->drafts->get(['node' => $node])->first();
		// Paths stay an object when empty, as in the main revisions.
		$params = [
			'content' => json_encode($content),
			'settings' => json_encode(['paths' => (object) ($settings['paths'] ?? [])] + $settings),
		];

		if (
			$current
			&& (bool) $this->db->revisions->holds(['revision' => $current['revision'], ...$params])->one()['holds']
		) {
			if ($rebase) {
				$this->point($node, (int) $current['revision'], $main, true);
			}

			return null;
		}

		$revision = (int) $this->db->revisions->create([
			'node' => $node,
			'base' => $current ? (int) $current['revision'] : $main,
			'editor' => $editor,
			...$params,
		])->one()['revision'];
		$this->point($node, $revision, $main, $rebase);

		return $revision;
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
	 * Drops the working copy, if there is one, and returns the revision it
	 * held, which stays in the history.
	 */
	public function delete(int $node): ?int
	{
		$row = $this->db->drafts->delete(['node' => $node])->first();

		return $row ? (int) $row['revision'] : null;
	}

	private function point(int $node, int $revision, ?int $main, bool $rebase): void
	{
		$this->db->drafts->upsert([
			'node' => $node,
			'revision' => $revision,
			'base' => $main,
			'rebase' => $rebase,
		])->run();
	}
}

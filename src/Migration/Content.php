<?php

declare(strict_types=1);

namespace Cosray\Migration;

use Celema\Quma\Database;

/**
 * Rewrites stored node content for a content migration: every revision,
 * main versions, working copies and history alike, the users' unsaved
 * edits, and the content `nodes` materializes from the main revision, in
 * one transaction. The content keeps its meaning and only changes shape,
 * so change times stay and no event is recorded. Titles, the search
 * index and the reference indexes are derived and stay as they are; run
 * `db:titles`, `db:fulltext` or `db:references` when the rewrite changes
 * what they read.
 */
final class Content
{
	private const int PAGE = 500;

	public function __construct(
		private readonly Database $db,
	) {}

	/**
	 * Hands each stored content of the given types, or of all, to
	 * `$rewrite` with its type handle; a returned array replaces it, null
	 * leaves it. Returns how many contents changed.
	 *
	 * @param callable(array<string, mixed>, string): ?array<string, mixed> $rewrite
	 * @param ?list<string> $types type handles
	 */
	public function rewrite(callable $rewrite, ?array $types = null): int
	{
		$filter = $types === null ? null : json_encode(array_values($types), JSON_THROW_ON_ERROR);

		return $this->db->transaction(function () use ($rewrite, $filter): int {
			$count = 0;
			$after = 0;

			do {
				$rows = $this->db->content->revisions([
					'after' => $after,
					'types' => $filter,
					'limit' => self::PAGE,
				])->all();

				foreach ($rows as $row) {
					$after = (int) $row['revision'];
					$content = self::rewritten($rewrite, (string) $row['content'], (string) $row['type']);

					if ($content !== null) {
						$this->db->content->revision(['revision' => $after, 'content' => $content])->run();
						$count++;
					}
				}
			} while (count($rows) === self::PAGE);

			$node = 0;
			$editor = 0;

			do {
				$rows = $this->db->content->edits([
					'node' => $node,
					'editor' => $editor,
					'types' => $filter,
					'limit' => self::PAGE,
				])->all();

				foreach ($rows as $row) {
					$node = (int) $row['node'];
					$editor = (int) $row['editor'];
					$content = self::rewritten($rewrite, (string) $row['content'], (string) $row['type']);

					if ($content !== null) {
						$this->db->content->edit(['node' => $node, 'editor' => $editor, 'content' => $content])->run();
						$count++;
					}
				}
			} while (count($rows) === self::PAGE);

			$this->db->content->materialize()->run();

			return $count;
		});
	}

	/**
	 * The rewritten content as JSON, or null when it stays as it is.
	 *
	 * @param callable(array<string, mixed>, string): ?array<string, mixed> $rewrite
	 */
	private static function rewritten(callable $rewrite, string $json, string $type): ?string
	{
		$content = json_decode($json, true);

		if (!is_array($content)) {
			return null;
		}

		/** @var array<string, mixed> $content */
		$result = $rewrite($content, $type);

		return $result === null || $result === $content ? null : json_encode($result, JSON_THROW_ON_ERROR);
	}
}

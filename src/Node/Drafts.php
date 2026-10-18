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

	/**
	 * Whether a working copy would leave the live node as it is: the same
	 * content, handle and active URL paths, compared by self::essence().
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
				&& self::essence($livePaths) === self::essence(array_map(trim(...), $paths))
				&& self::essence($liveContent) === self::essence($content)
		);
	}

	/**
	 * Content as far as an editor can tell it apart. The form submits
	 * every slot it shows, numbers as text, so empty values and fields
	 * holding nothing but empty values count as absent, and 1041, 1041.0
	 * and '1041' are the same; key order does not count either.
	 */
	private static function essence(mixed $value): mixed
	{
		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}

		if ($value === '') {
			return null;
		}

		if (!is_array($value)) {
			return $value;
		}

		if (array_is_list($value)) {
			return $value === [] ? null : array_map(self::essence(...), $value);
		}

		$essence = array_filter(
			array_map(self::essence(...), $value),
			static fn(mixed $item): bool => $item !== null,
		);

		$field = array_key_exists('type', $value) && array_key_exists('value', $value);

		if ($field && !isset($essence['value']) && !isset($essence['meta'])) {
			return null;
		}

		ksort($essence);

		return $essence === [] ? null : $essence;
	}

	/**
	 * Deletes the working copy; its history keeps the last state and, when
	 * the caller says the content went live, records it as published rather
	 * than discarded. Must run inside the caller's transaction: the reason
	 * travels as a transaction-local setting the history trigger reads.
	 */
	public function delete(int $node, bool $published = false): void
	{
		// Set either way: an earlier delete in the same transaction may have
		// left `published` behind.
		$this->db->drafts->outcome(['outcome' => $published ? 'published' : 'discarded'])->run();
		$this->db->drafts->delete(['node' => $node])->run();
	}
}

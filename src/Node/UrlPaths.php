<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celemas\Quma\Database;

/**
 * Resolves node uids to their active URL paths. Used by the richtext
 * renderer to turn `link.node` references into hrefs at render time.
 */
final class UrlPaths
{
	/** @var array<string, array<string, string>> */
	private array $cache = [];

	public function __construct(
		private readonly Database $db,
	) {}

	/** @return array<string, string> Locale id to active path. */
	public function map(string $uid): array
	{
		if (isset($this->cache[$uid])) {
			return $this->cache[$uid];
		}

		$map = [];

		foreach ($this->db->paths->activeByNodeUid(['uid' => $uid])->all() as $row) {
			$map[(string) $row['locale']] = (string) $row['path'];
		}

		return $this->cache[$uid] = $map;
	}
}

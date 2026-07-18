<?php

declare(strict_types=1);

namespace Cosray\References;

use Celema\Quma\Database;

/**
 * Keeps the derived reference indexes (`asset_references`,
 * `node_references`) in step with an owner's content: full replace per
 * owner, no diffing. Inserts join against the target tables, so
 * dangling uids are skipped instead of tripping the FK.
 */
final class Sync
{
	public function __construct(
		private readonly Database $db,
	) {}

	/** @param array{assets: list<string>, nodes: list<string>} $refs */
	public function replace(string $ownerType, string $ownerUid, array $refs): void
	{
		$this->remove($ownerType, $ownerUid);
		$owner = ['ownerType' => $ownerType, 'ownerUid' => $ownerUid];

		if ($refs['assets'] !== []) {
			$this->db->references->insertAssets([
				...$owner,
				'uids' => json_encode($refs['assets']),
			])->run();
		}

		if ($refs['nodes'] !== []) {
			$this->db->references->insertNodes([
				...$owner,
				'uids' => json_encode($refs['nodes']),
			])->run();
		}
	}

	public function remove(string $ownerType, string $ownerUid): void
	{
		$this->db->references->deleteOwner([
			'ownerType' => $ownerType,
			'ownerUid' => $ownerUid,
		])->run();
	}
}

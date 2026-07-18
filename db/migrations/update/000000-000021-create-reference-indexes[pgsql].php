<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000021_CreateReferenceIndexes;

use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\References\Rebuild;

/**
 * Creates the derived reference indexes (`asset_references`,
 * `node_references`) and populates them from live node content and
 * menu item images — the same code path as `php run db:references`,
 * which also remains the recovery tool after restores or imports.
 */
final class Migration implements Contract\Migration
{
	private const string DDL = <<<'SQL'
		CREATE TABLE /*:cms.prefix:*/asset_references (
			owner_type text NOT NULL,
			owner_uid text NOT NULL,
			asset_uid text NOT NULL,
			CONSTRAINT /*:cms.obj:*/pk_asset_references PRIMARY KEY (owner_type, owner_uid, asset_uid),
			CONSTRAINT /*:cms.obj:*/fk_asset_references_assets FOREIGN KEY (asset_uid)
				REFERENCES /*:cms.prefix:*/assets (uid) ON DELETE RESTRICT,
			CONSTRAINT /*:cms.obj:*/ck_asset_references_owner_type CHECK (char_length(owner_type) <= 32),
			CONSTRAINT /*:cms.obj:*/ck_asset_references_owner_uid CHECK (char_length(owner_uid) <= 64)
		);
		CREATE INDEX /*:cms.obj:*/ix_asset_references_asset
			ON /*:cms.prefix:*/asset_references USING btree (asset_uid);

		CREATE TABLE /*:cms.prefix:*/node_references (
			owner_type text NOT NULL,
			owner_uid text NOT NULL,
			target_uid text NOT NULL,
			CONSTRAINT /*:cms.obj:*/pk_node_references PRIMARY KEY (owner_type, owner_uid, target_uid),
			CONSTRAINT /*:cms.obj:*/fk_node_references_nodes FOREIGN KEY (target_uid)
				REFERENCES /*:cms.prefix:*/nodes (uid),
			CONSTRAINT /*:cms.obj:*/ck_node_references_owner_type CHECK (char_length(owner_type) <= 32),
			CONSTRAINT /*:cms.obj:*/ck_node_references_owner_uid CHECK (char_length(owner_uid) <= 64)
		);
		CREATE INDEX /*:cms.obj:*/ix_node_references_target
			ON /*:cms.prefix:*/node_references USING btree (target_uid);
		SQL;

	public function run(Environment $env): void
	{
		$sql = $env->conn->config->placeholders?->compileSql(self::DDL, __FILE__) ?? self::DDL;
		$env->db->execute($sql)->run();

		$result = new Rebuild($env->db)->run();

		echo "Reference indexes populated: {$result['assets']} asset references, "
			. "{$result['nodes']} node references from {$result['owners']} owners"
			. ($result['skipped'] > 0 ? " ({$result['skipped']} dangling uids skipped)" : '')
			. "\n";
	}
}

return Migration::class;

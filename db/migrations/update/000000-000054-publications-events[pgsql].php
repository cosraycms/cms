<?php

declare(strict_types=1);

namespace Quma\Migrations\M000000_000054_PublicationsEvents;

use Celema\Container\Container;
use Celema\Quma\Contract;
use Celema\Quma\Environment;
use Cosray\Node\Publications;

/**
 * Moves `published` and `hidden` out of `nodes` into `publications`, with
 * a row for every node of a type that has publication, starts the
 * `events` log with a `created` event per node, renames `node_positions`,
 * `node_handles` and `autosaves`, and drops the node triggers that kept
 * the books: the store sets `changed`, `edited` and the editor from now
 * on. Record types are known only in PHP, so a registered record type gets
 * no rows; nodes of a type no registered class has keep their flags and
 * are counted.
 */
final class Migration implements Contract\Migration
{
	public function __construct(
		private readonly Container $container,
	) {}

	public function run(Environment $env): void
	{
		$publications = Publications::of($this->container, $env->db);
		$records = json_encode($publications->records(), JSON_THROW_ON_ERROR);
		$known = json_encode([...$publications->records(), ...$publications->publishable()], JSON_THROW_ON_ERROR);

		$env->db->execute($this->sql($env, <<<'SQL'
			DROP TRIGGER /*:cms.obj:*/nodes_trigger_02_change ON /*:cms.prefix:*/nodes;
			DROP TRIGGER /*:cms.obj:*/nodes_trigger_03_history ON /*:cms.prefix:*/nodes;
			DROP TRIGGER /*:cms.obj:*/nodes_trigger_04_edited ON /*:cms.prefix:*/nodes;
			DROP FUNCTION /*:cms.prefix:*/record_node_history();
			DROP FUNCTION /*:cms.prefix:*/update_node_edited();

			ALTER TABLE /*:cms.prefix:*/node_positions RENAME TO positions;
			ALTER TABLE /*:cms.prefix:*/positions
				RENAME CONSTRAINT /*:cms.obj:*/fk_node_positions_nodes TO /*:cms.obj:*/fk_positions_nodes;
			ALTER TABLE /*:cms.prefix:*/positions
				RENAME CONSTRAINT /*:cms.obj:*/fk_node_positions_parent TO /*:cms.obj:*/fk_positions_parent;
			ALTER TABLE /*:cms.prefix:*/positions
				RENAME CONSTRAINT /*:cms.obj:*/ck_node_positions_scope TO /*:cms.obj:*/ck_positions_scope;
			ALTER INDEX /*:cms.prefix:*/ux_node_positions_parent RENAME TO /*:cms.obj:*/ux_positions_parent;
			ALTER INDEX /*:cms.prefix:*/ux_node_positions_collection RENAME TO /*:cms.obj:*/ux_positions_collection;
			ALTER INDEX /*:cms.prefix:*/ix_node_positions_node RENAME TO /*:cms.obj:*/ix_positions_node;

			DROP TRIGGER /*:cms.obj:*/node_handles_trigger_01_change ON /*:cms.prefix:*/node_handles;
			ALTER TABLE /*:cms.prefix:*/node_handles DROP COLUMN editor;
			ALTER TABLE /*:cms.prefix:*/node_handles DROP COLUMN changed;
			ALTER TABLE /*:cms.prefix:*/node_handles RENAME TO handles;
			ALTER TABLE /*:cms.prefix:*/handles
				RENAME CONSTRAINT /*:cms.obj:*/pk_node_handles TO /*:cms.obj:*/pk_handles;
			ALTER TABLE /*:cms.prefix:*/handles
				RENAME CONSTRAINT /*:cms.obj:*/uc_node_handles_node TO /*:cms.obj:*/uc_handles_node;
			ALTER TABLE /*:cms.prefix:*/handles
				RENAME CONSTRAINT /*:cms.obj:*/fk_node_handles_nodes TO /*:cms.obj:*/fk_handles_nodes;
			ALTER TABLE /*:cms.prefix:*/handles
				RENAME CONSTRAINT /*:cms.obj:*/fk_node_handles_users_creator TO /*:cms.obj:*/fk_handles_users_creator;
			ALTER TABLE /*:cms.prefix:*/handles
				RENAME CONSTRAINT /*:cms.obj:*/ck_node_handles_handle TO /*:cms.obj:*/ck_handles_handle;

			ALTER TABLE /*:cms.prefix:*/autosaves RENAME TO edits;
			ALTER TABLE /*:cms.prefix:*/edits RENAME CONSTRAINT /*:cms.obj:*/pk_autosaves TO /*:cms.obj:*/pk_edits;
			ALTER TABLE /*:cms.prefix:*/edits
				RENAME CONSTRAINT /*:cms.obj:*/fk_autosaves_nodes TO /*:cms.obj:*/fk_edits_nodes;
			ALTER TABLE /*:cms.prefix:*/edits
				RENAME CONSTRAINT /*:cms.obj:*/fk_autosaves_users TO /*:cms.obj:*/fk_edits_users;
			ALTER INDEX /*:cms.prefix:*/ix_autosaves_changed RENAME TO /*:cms.obj:*/ix_edits_changed;
			ALTER TRIGGER /*:cms.obj:*/autosaves_trigger_01_change ON /*:cms.prefix:*/edits
				RENAME TO /*:cms.obj:*/edits_trigger_01_change;

			CREATE TABLE /*:cms.prefix:*/publications (
				node bigint NOT NULL,
				published boolean NOT NULL DEFAULT false,
				hidden boolean NOT NULL DEFAULT false,
				CONSTRAINT /*:cms.obj:*/pk_publications PRIMARY KEY (node),
				CONSTRAINT /*:cms.obj:*/fk_publications_nodes FOREIGN KEY (node)
					REFERENCES /*:cms.prefix:*/nodes (node) ON DELETE CASCADE
			);

			CREATE TABLE /*:cms.prefix:*/events (
				event bigint GENERATED ALWAYS AS IDENTITY,
				node bigint NOT NULL,
				at timestamp with time zone NOT NULL DEFAULT clock_timestamp(),
				actor bigint NOT NULL,
				action text NOT NULL,
				data jsonb NOT NULL DEFAULT '{}',
				CONSTRAINT /*:cms.obj:*/pk_events PRIMARY KEY (event),
				CONSTRAINT /*:cms.obj:*/fk_events_nodes FOREIGN KEY (node)
					REFERENCES /*:cms.prefix:*/nodes (node) ON DELETE CASCADE,
				CONSTRAINT /*:cms.obj:*/fk_events_users FOREIGN KEY (actor)
					REFERENCES /*:cms.prefix:*/users (usr),
				CONSTRAINT /*:cms.obj:*/ck_events_action CHECK (action IN (
					'created', 'saved', 'drafted', 'applied', 'discarded',
					'published', 'unpublished', 'hidden', 'shown',
					'moved', 'deleted', 'restored', 'locked', 'unlocked'
				))
			);
			CREATE INDEX /*:cms.obj:*/ix_events_node ON /*:cms.prefix:*/events (node, event);

			INSERT INTO /*:cms.prefix:*/events (node, actor, action, at)
			SELECT node, creator, 'created', created
			FROM /*:cms.prefix:*/nodes
			ORDER BY created, node;
			SQL))->run();

		$env->db->execute(
			$this->sql($env, <<<'SQL'
				INSERT INTO /*:cms.prefix:*/publications (node, published, hidden)
				SELECT n.node, n.published, n.hidden
				FROM /*:cms.prefix:*/nodes n
				JOIN /*:cms.prefix:*/types t ON t.type = n.type
				WHERE t.handle NOT IN (SELECT jsonb_array_elements_text(:records::jsonb))
				SQL),
			['records' => $records],
		)->run();

		$unknown = (int) $env->db->execute(
			$this->sql($env, <<<'SQL'
				SELECT count(*) AS count
				FROM /*:cms.prefix:*/nodes n
				JOIN /*:cms.prefix:*/types t ON t.type = n.type
				WHERE t.handle NOT IN (SELECT jsonb_array_elements_text(:known::jsonb))
				SQL),
			['known' => $known],
		)->one()['count'];

		$env->db->execute($this->sql($env, <<<'SQL'
			ALTER TABLE /*:cms.prefix:*/nodes DROP COLUMN published;
			ALTER TABLE /*:cms.prefix:*/nodes DROP COLUMN hidden;
			SQL))->run();

		if ($unknown > 0) {
			echo "Kept the publication of {$unknown} nodes of unregistered node types\n";
		}
	}

	private function sql(Environment $env, string $sql): string
	{
		return $env->conn->config->placeholders?->compileSql($sql, __FILE__) ?? $sql;
	}
}

return Migration::class;

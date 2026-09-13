-- Working copies of published nodes. `content` keeps the exact shape of
-- nodes.content so content migrations rewrite both tables the same way;
-- `settings` holds the drafted handle and URL paths
-- ({"handle": ?text, "paths": {locale: path}}). `created` is when the
-- working copy diverged from the live row.

ALTER TABLE /*:cms.prefix:*/drafts
	ALTER COLUMN changed SET DEFAULT now(),
	ADD COLUMN created timestamp with time zone NOT NULL DEFAULT now(),
	ADD COLUMN settings jsonb NOT NULL DEFAULT '{}',
	ADD CONSTRAINT /*:cms.obj:*/fk_drafts_users_editor FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr);

CREATE TRIGGER /*:cms.obj:*/drafts_trigger_02_change BEFORE UPDATE
	ON /*:cms.prefix:*/drafts
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();

-- Publishing or discarding deletes the working copy; its history goes with it.
ALTER TABLE /*:cms.prefix:*/drafts_history
	DROP CONSTRAINT /*:cms.obj:*/fk_drafts_history_drafts,
	ADD CONSTRAINT /*:cms.obj:*/fk_drafts_history_drafts FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/drafts (node) ON DELETE CASCADE;

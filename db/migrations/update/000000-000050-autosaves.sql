-- An editor's unsaved edits of a node, kept for restoring after a reload,
-- a closed tab or a crash: one row per node and user, never validated and
-- never live. `content` and `settings` keep the shapes of the `drafts`
-- columns, so content migrations rewrite them the same way. `base` is the
-- `changed` of the node or working copy the edits started from.
CREATE TABLE /*:cms.prefix:*/autosaves (
	node bigint NOT NULL,
	editor bigint NOT NULL,
	changed timestamp with time zone NOT NULL DEFAULT now(),
	base timestamp with time zone NOT NULL,
	content jsonb NOT NULL,
	settings jsonb NOT NULL DEFAULT '{}',
	CONSTRAINT /*:cms.obj:*/pk_autosaves PRIMARY KEY (node, editor),
	CONSTRAINT /*:cms.obj:*/fk_autosaves_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node) ON DELETE CASCADE,
	CONSTRAINT /*:cms.obj:*/fk_autosaves_users FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr) ON DELETE CASCADE
);
CREATE INDEX /*:cms.obj:*/ix_autosaves_changed ON /*:cms.prefix:*/autosaves (changed);
CREATE TRIGGER /*:cms.obj:*/autosaves_trigger_01_change BEFORE UPDATE
	ON /*:cms.prefix:*/autosaves
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();

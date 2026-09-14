-- A working copy's history outlives the working copy. Publishing or
-- discarding records its last state and keeps every earlier one, so a
-- node's timeline can say who saved what while the changes were pending;
-- the drafted handle and paths ride along in `settings`.

ALTER TABLE /*:cms.prefix:*/drafts_history
	DROP CONSTRAINT /*:cms.obj:*/fk_drafts_history_drafts,
	ADD COLUMN settings jsonb NOT NULL DEFAULT '{}',
	ADD CONSTRAINT /*:cms.obj:*/fk_drafts_history_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node);

CREATE OR REPLACE FUNCTION /*:cms.prefix:*/record_draft_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO /*:cms.prefix:*/drafts_history (
		node, changed, editor, content, settings
	) VALUES (
		OLD.node, OLD.changed, OLD.editor, OLD.content, OLD.settings
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate draft history row skipped. draft: %, changed: %', OLD.node, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER /*:cms.obj:*/drafts_trigger_01_history ON /*:cms.prefix:*/drafts;
CREATE TRIGGER /*:cms.obj:*/drafts_trigger_01_history AFTER UPDATE OR DELETE
	ON /*:cms.prefix:*/drafts FOR EACH ROW EXECUTE FUNCTION
	/*:cms.prefix:*/record_draft_history();

-- Statement time, not transaction time: the history tables key their rows
-- by `changed`, and two writes to one row inside a transaction must both
-- keep theirs.
CREATE OR REPLACE FUNCTION /*:cms.prefix:*/update_changed_column()
	RETURNS TRIGGER AS $$
BEGIN
   NEW.changed = clock_timestamp();
   RETURN NEW;
END;
$$ LANGUAGE plpgsql;

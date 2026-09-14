-- A working copy's history outlives the working copy. Publishing or
-- discarding records its last state and keeps every earlier one, so a
-- node's timeline can say who saved what while the changes were pending.
-- `created` is copied from the draft row and keys the rows of one working
-- copy together; `outcome` says why a row was superseded: `saved` by the
-- next save, or — on the row written when the draft is deleted — the
-- reason it went, `published` when the store says so through the
-- transaction-local setting `cosray.draft_outcome`, `discarded` otherwise.
-- The drafted handle and paths ride along in `settings`.

ALTER TABLE /*:cms.prefix:*/drafts_history
	DROP CONSTRAINT /*:cms.obj:*/fk_drafts_history_drafts,
	ADD COLUMN created timestamp with time zone NOT NULL DEFAULT now(),
	ADD COLUMN settings jsonb NOT NULL DEFAULT '{}',
	ADD COLUMN outcome text NOT NULL DEFAULT 'saved',
	ADD CONSTRAINT /*:cms.obj:*/fk_drafts_history_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node),
	ADD CONSTRAINT /*:cms.obj:*/ck_drafts_history_outcome
		CHECK (outcome IN ('saved', 'published', 'discarded'));

CREATE OR REPLACE FUNCTION /*:cms.prefix:*/record_draft_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO /*:cms.prefix:*/drafts_history (
		node, created, changed, editor, content, settings, outcome
	) VALUES (
		OLD.node, OLD.created, OLD.changed, OLD.editor, OLD.content, OLD.settings,
		CASE WHEN TG_OP = 'DELETE'
			THEN coalesce(nullif(current_setting('cosray.draft_outcome', true), ''), 'discarded')
			ELSE 'saved'
		END
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

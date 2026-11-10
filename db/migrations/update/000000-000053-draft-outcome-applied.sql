-- An applied draft no longer means a published node: the main version
-- it went into may stay unpublished.
ALTER TABLE /*:cms.prefix:*/drafts_history DROP CONSTRAINT /*:cms.obj:*/ck_drafts_history_outcome;
UPDATE /*:cms.prefix:*/drafts_history SET outcome = 'applied' WHERE outcome = 'published';
ALTER TABLE /*:cms.prefix:*/drafts_history ADD CONSTRAINT /*:cms.obj:*/ck_drafts_history_outcome
	CHECK (outcome IN ('saved', 'applied', 'discarded'));

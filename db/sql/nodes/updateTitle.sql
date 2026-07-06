-- Bulk title rewrite used by the title rebuild. Callers disable the node
-- change/history triggers around this so a rebuild is not recorded as an edit.
UPDATE
	/*:cms.prefix:*/nodes
SET
	title = :title::jsonb
WHERE
	uid = :uid;

-- Bulk title rewrite used by the title rebuild. It leaves `changed` and
-- `edited` alone, so a rebuild is not recorded as an edit.
UPDATE
	/*:cms.prefix:*/nodes
SET
	title = :title::jsonb
WHERE
	uid = :uid;

UPDATE
	/*:cms.prefix:*/revisions
SET
	content = :content::jsonb
WHERE
	revision = :revision;

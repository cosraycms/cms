UPDATE
	/*:cms.prefix:*/nodes
SET
	published = :published,
	-- Going online for the first time is the first publication.
	released = CASE WHEN :published::boolean AND released IS NULL THEN now() ELSE released END,
	editor = :editor
WHERE
	uid = :uid
	AND deleted IS NULL;

UPDATE
	/*:cms.prefix:*/nodes
SET
	published = :published,
	editor = :editor
WHERE
	uid = :uid
	AND deleted IS NULL;

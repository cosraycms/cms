SELECT
	r.revision,
	r.node,
	r.base,
	r.editor,
	r.created,
	r.content,
	r.settings
FROM
	/*:cms.prefix:*/revisions r
WHERE
	r.revision = :revision;

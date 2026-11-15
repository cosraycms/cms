SELECT
	d.node,
	d.revision,
	d.base,
	d.created,
	r.created AS changed,
	r.editor,
	r.content,
	r.settings
FROM
	/*:cms.prefix:*/drafts d
	INNER JOIN /*:cms.prefix:*/revisions r ON r.revision = d.revision
WHERE
	d.node = :node;

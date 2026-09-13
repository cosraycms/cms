SELECT
	d.node,
	d.content,
	d.settings,
	d.created,
	d.changed,
	d.editor
FROM
	/*:cms.prefix:*/drafts d
WHERE
	d.node = :node;

-- Rebuild source: the working copy of every live node that has one.
SELECT
	n.uid,
	r.content::text AS content
FROM
	/*:cms.prefix:*/drafts d
	INNER JOIN /*:cms.prefix:*/nodes n ON n.node = d.node
	INNER JOIN /*:cms.prefix:*/revisions r ON r.revision = d.revision
WHERE
	n.deleted IS NULL
ORDER BY
	d.node;

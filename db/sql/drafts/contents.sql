-- Rebuild source: the working copy of every live node that has one.
SELECT
	n.uid,
	d.content::text AS content
FROM
	/*:cms.prefix:*/drafts d
	INNER JOIN /*:cms.prefix:*/nodes n ON n.node = d.node
WHERE
	n.deleted IS NULL
ORDER BY
	d.node;

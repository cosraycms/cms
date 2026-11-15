-- Working copies left on nodes of the given types, which hold none.
SELECT
	n.uid,
	t.handle AS type
FROM
	/*:cms.prefix:*/drafts d
	INNER JOIN /*:cms.prefix:*/nodes n ON n.node = d.node
	INNER JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE
	t.handle IN (SELECT jsonb_array_elements_text(:types::jsonb))
	AND n.deleted IS NULL
ORDER BY
	t.handle,
	n.uid;

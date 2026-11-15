-- Nodes of record types that still have a publication row.
SELECT
	n.uid,
	t.handle AS type
FROM
	/*:cms.prefix:*/publications p
	INNER JOIN /*:cms.prefix:*/nodes n ON n.node = p.node
	INNER JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE
	t.handle IN (SELECT jsonb_array_elements_text(:types::jsonb))
ORDER BY
	t.handle,
	n.uid;

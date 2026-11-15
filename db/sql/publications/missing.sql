-- Nodes of types with publication that have no row.
SELECT
	n.uid,
	t.handle AS type
FROM
	/*:cms.prefix:*/nodes n
	INNER JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE
	t.handle IN (SELECT jsonb_array_elements_text(:types::jsonb))
	AND NOT EXISTS (SELECT FROM /*:cms.prefix:*/publications p WHERE p.node = n.node)
ORDER BY
	t.handle,
	n.uid;

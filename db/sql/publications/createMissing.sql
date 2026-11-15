INSERT INTO /*:cms.prefix:*/publications (node, published)
SELECT
	n.node,
	:published
FROM
	/*:cms.prefix:*/nodes n
	INNER JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE
	t.handle IN (SELECT jsonb_array_elements_text(:types::jsonb))

ON CONFLICT (node) DO NOTHING

RETURNING node;

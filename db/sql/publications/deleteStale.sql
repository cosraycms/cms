DELETE FROM
	/*:cms.prefix:*/publications p
USING
	/*:cms.prefix:*/nodes n,
	/*:cms.prefix:*/types t
WHERE
	n.node = p.node
	AND t.type = n.type
	AND t.handle IN (SELECT jsonb_array_elements_text(:types::jsonb))

RETURNING p.node;

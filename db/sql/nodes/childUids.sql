SELECT
	c.uid
FROM
	/*:cms.prefix:*/nodes c
	JOIN /*:cms.prefix:*/nodes p ON c.parent = p.node
WHERE
	p.uid = :uid
	AND c.deleted IS NULL;

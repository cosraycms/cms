SELECT
	n.node,
	t.handle AS type
FROM
	/*:cms.prefix:*/nodes n
	JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE
	n.uid = :uid;

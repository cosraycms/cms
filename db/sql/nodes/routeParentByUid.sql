SELECT
	n.node,
	n.parent,
	n.uid,
	h.handle,
	n.content
FROM
	/*:cms.prefix:*/nodes n
	LEFT JOIN /*:cms.prefix:*/handles h ON
		h.node = n.node
WHERE
	n.uid = :uid
	AND n.deleted IS NULL
LIMIT 1;

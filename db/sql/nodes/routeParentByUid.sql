SELECT
	n.node,
	n.uid,
	h.handle,
	n.content
FROM
	/*:cms.prefix:*/nodes n
	LEFT JOIN /*:cms.prefix:*/node_handles h ON
		h.node = n.node
WHERE
	n.uid = :uid
	AND n.deleted IS NULL
LIMIT 1;

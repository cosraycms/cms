SELECT
	n.uid,
	h.handle,
	n.content
FROM
	/*:cms.prefix:*/nodes n
	LEFT JOIN /*:cms.prefix:*/node_handles h ON
		h.node = n.node
WHERE
	n.node = :node
	AND n.deleted IS NULL
LIMIT 1;

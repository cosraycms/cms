SELECT
	r.owner_type AS "ownerType",
	r.owner_uid AS "ownerUid",
	t.handle AS "nodeType",
	n.published,
	n.content -> 'title' -> 'value' AS title
FROM
	/*:cms.prefix:*/node_references r
	LEFT JOIN /*:cms.prefix:*/nodes n ON r.owner_type = 'node' AND n.uid = r.owner_uid
	LEFT JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE
	r.target_uid = :uid
ORDER BY
	r.owner_type,
	r.owner_uid;

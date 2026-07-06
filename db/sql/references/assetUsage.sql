SELECT
	r.owner_type AS "ownerType",
	r.owner_uid AS "ownerUid",
	t.handle AS "nodeType",
	n.published,
	COALESCE(n.content -> 'title' -> 'value', m.data -> 'title') AS title
FROM
	/*:cms.prefix:*/asset_references r
	LEFT JOIN /*:cms.prefix:*/nodes n ON r.owner_type = 'node' AND n.uid = r.owner_uid
	LEFT JOIN /*:cms.prefix:*/types t ON t.type = n.type
	LEFT JOIN /*:cms.prefix:*/menu_items m ON r.owner_type = 'menu' AND m.item = r.owner_uid
WHERE
	r.asset_uid = :uid
ORDER BY
	r.owner_type,
	r.owner_uid;

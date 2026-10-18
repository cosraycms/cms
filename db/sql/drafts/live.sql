-- The live state a working copy is compared with.
SELECT
	n.content,
	h.handle,
	coalesce(
		(
			SELECT
				jsonb_object_agg(p.locale, p.path)
			FROM
				/*:cms.prefix:*/url_paths p
			WHERE
				p.node = n.node
				AND p.inactive IS NULL
		),
		'{}'
	) AS paths
FROM
	/*:cms.prefix:*/nodes n
	LEFT JOIN /*:cms.prefix:*/node_handles h ON h.node = n.node
WHERE
	n.node = :node;

-- Whether the node's stored content, handle and active paths are exactly
-- what the revision holds.
SELECT
	n.content = r.content
	AND jsonb_build_object(
		'handle', h.handle,
		'paths', coalesce(
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
		)
	) = jsonb_build_object(
		'handle', r.settings->'handle',
		'paths', CASE WHEN jsonb_typeof(r.settings->'paths') = 'object' THEN r.settings->'paths' ELSE '{}' END
	) AS matches
FROM
	/*:cms.prefix:*/nodes n
	INNER JOIN /*:cms.prefix:*/revisions r ON r.revision = :revision
	LEFT JOIN /*:cms.prefix:*/handles h ON h.node = n.node
WHERE
	n.node = :node;

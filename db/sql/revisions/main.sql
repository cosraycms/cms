-- Makes the node's stored state, its content, handle and active paths, a
-- new revision and points the node at it.
WITH revision AS (
	INSERT INTO /*:cms.prefix:*/revisions (
		node,
		base,
		editor,
		created,
		content,
		settings
	)
	SELECT
		n.node,
		:base,
		:editor,
		coalesce(CAST(:created AS timestamptz), clock_timestamp()),
		n.content,
		jsonb_build_object(
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
		)
	FROM
		/*:cms.prefix:*/nodes n
		LEFT JOIN /*:cms.prefix:*/handles h ON h.node = n.node
	WHERE
		n.node = :node

	RETURNING
		revision,
		node
)
UPDATE
	/*:cms.prefix:*/nodes n
SET
	revision = revision.revision
FROM
	revision
WHERE
	n.node = revision.node

RETURNING n.revision;

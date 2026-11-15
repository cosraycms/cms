-- Says whether the row is new, which starts an editing session.
INSERT INTO /*:cms.prefix:*/edits (
	node,
	editor,
	base,
	content,
	settings
) VALUES (
	:node,
	:editor,
	-- A revision of another node, or none at all, is no base.
	(SELECT r.revision FROM /*:cms.prefix:*/revisions r WHERE r.revision = :base::bigint AND r.node = :node),
	:content::jsonb,
	:settings::jsonb
)
ON CONFLICT (node, editor) DO UPDATE SET
	changed = clock_timestamp(),
	base = EXCLUDED.base,
	content = EXCLUDED.content,
	settings = EXCLUDED.settings
WHERE
	(/*:cms.prefix:*/edits.base, /*:cms.prefix:*/edits.content, /*:cms.prefix:*/edits.settings)
		IS DISTINCT FROM (EXCLUDED.base, EXCLUDED.content, EXCLUDED.settings)

RETURNING (xmax = 0) AS created;

-- Returns no row when the working copy already holds this state, so an
-- unchanged save writes no history row.
INSERT INTO /*:cms.prefix:*/drafts (
	node,
	content,
	settings,
	editor
) VALUES (
	:node,
	:content::jsonb,
	:settings::jsonb,
	:editor
)
ON CONFLICT (node) DO UPDATE SET
	content = EXCLUDED.content,
	settings = EXCLUDED.settings,
	editor = EXCLUDED.editor
WHERE
	(/*:cms.prefix:*/drafts.content, /*:cms.prefix:*/drafts.settings)
		IS DISTINCT FROM (EXCLUDED.content, EXCLUDED.settings)

RETURNING node;

-- Says whether the row is new, which starts an editing session.
INSERT INTO /*:cms.prefix:*/autosaves (
	node,
	editor,
	base,
	content,
	settings
) VALUES (
	:node,
	:editor,
	:base::timestamptz,
	:content::jsonb,
	:settings::jsonb
)
ON CONFLICT (node, editor) DO UPDATE SET
	base = EXCLUDED.base,
	content = EXCLUDED.content,
	settings = EXCLUDED.settings
WHERE
	(/*:cms.prefix:*/autosaves.base, /*:cms.prefix:*/autosaves.content, /*:cms.prefix:*/autosaves.settings)
		IS DISTINCT FROM (EXCLUDED.base, EXCLUDED.content, EXCLUDED.settings)

RETURNING (xmax = 0) AS created;

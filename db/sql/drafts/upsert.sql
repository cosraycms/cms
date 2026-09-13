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
	editor = EXCLUDED.editor;

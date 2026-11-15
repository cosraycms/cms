INSERT INTO /*:cms.prefix:*/revisions (
	node,
	base,
	editor,
	content,
	settings
) VALUES (
	:node,
	:base,
	:editor,
	:content::jsonb,
	:settings::jsonb
)

RETURNING revision;

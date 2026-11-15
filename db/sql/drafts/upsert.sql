-- Points the working copy at a revision. The first one dates when the
-- copy diverged and the main revision it is up to date with; later ones
-- keep both unless `rebase` moves the base onto the given one.
INSERT INTO /*:cms.prefix:*/drafts (
	node,
	revision,
	base,
	created
) VALUES (
	:node,
	:revision,
	:base,
	clock_timestamp()
)
ON CONFLICT (node) DO UPDATE SET
	revision = EXCLUDED.revision,
	base = CASE WHEN :rebase::boolean THEN EXCLUDED.base ELSE /*:cms.prefix:*/drafts.base END;

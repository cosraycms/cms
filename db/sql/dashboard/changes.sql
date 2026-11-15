-- Live nodes with a working copy, and how many of those changed lately.
SELECT
	count(*) AS total,
	count(*) FILTER (WHERE r.created >= now() - interval '7 days') AS recent
FROM /*:cms.prefix:*/drafts d
	INNER JOIN /*:cms.prefix:*/nodes n ON n.node = d.node
	INNER JOIN /*:cms.prefix:*/revisions r ON r.revision = d.revision
WHERE n.deleted IS NULL;

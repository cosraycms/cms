SELECT
	n.uid,
	n.published,
	EXISTS (SELECT 1 FROM /*:cms.prefix:*/drafts d WHERE d.node = n.node) AS has_draft,
	n.changed,
	n.title,
	t.handle AS type
FROM /*:cms.prefix:*/nodes n
INNER JOIN /*:cms.prefix:*/types t USING(type)
WHERE n.deleted IS NULL
ORDER BY
	n.changed DESC,
	n.node DESC
LIMIT :limit;

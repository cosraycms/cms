-- A page of revisions to rewrite, of the given types or of all.
SELECT
	r.revision,
	t.handle AS type,
	r.content
FROM
	/*:cms.prefix:*/revisions r
	INNER JOIN /*:cms.prefix:*/nodes n ON n.node = r.node
	INNER JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE
	r.revision > :after
	AND (:types::jsonb IS NULL OR t.handle IN (SELECT jsonb_array_elements_text(:types::jsonb)))
ORDER BY
	r.revision
LIMIT :limit;

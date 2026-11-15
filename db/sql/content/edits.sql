-- A page of unsaved edits to rewrite, of the given types or of all.
SELECT
	e.node,
	e.editor,
	t.handle AS type,
	e.content
FROM
	/*:cms.prefix:*/edits e
	INNER JOIN /*:cms.prefix:*/nodes n ON n.node = e.node
	INNER JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE
	(e.node, e.editor) > (:node, :editor)
	AND (:types::jsonb IS NULL OR t.handle IN (SELECT jsonb_array_elements_text(:types::jsonb)))
ORDER BY
	e.node,
	e.editor
LIMIT :limit;

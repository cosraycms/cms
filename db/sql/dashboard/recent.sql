SELECT
	n.uid,
	p.published,
	n.changed,
	n.edited,
	coalesce(ue.data->>'name', ue.username, ue.email) AS editor_name,
	d.changed AS draft_changed,
	coalesce(ud.data->>'name', ud.username, ud.email) AS draft_editor_name,
	m.changed AS mine_changed,
	m.base AS mine_base,
	n.title,
	t.handle AS type
FROM /*:cms.prefix:*/nodes n
INNER JOIN /*:cms.prefix:*/types t USING(type)
LEFT JOIN /*:cms.prefix:*/publications p ON p.node = n.node
INNER JOIN /*:cms.prefix:*/users ue ON ue.usr = n.editor
LEFT JOIN /*:cms.prefix:*/drafts d ON d.node = n.node
LEFT JOIN /*:cms.prefix:*/users ud ON ud.usr = d.editor
LEFT JOIN /*:cms.prefix:*/edits m ON m.node = n.node AND m.editor = :mine
WHERE n.deleted IS NULL
ORDER BY
	n.changed DESC,
	n.node DESC
LIMIT :limit;

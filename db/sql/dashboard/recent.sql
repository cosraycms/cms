SELECT
	n.uid,
	p.published,
	n.changed,
	n.edited,
	n.revision,
	coalesce(ua.data->>'name', ua.username, ua.email) AS applier_name,
	d.revision AS draft_revision,
	dr.created AS draft_changed,
	coalesce(ud.data->>'name', ud.username, ud.email) AS draft_editor_name,
	m.changed AS mine_changed,
	m.base AS mine_base,
	n.title,
	t.handle AS type
FROM /*:cms.prefix:*/nodes n
INNER JOIN /*:cms.prefix:*/types t USING(type)
LEFT JOIN /*:cms.prefix:*/publications p ON p.node = n.node
LEFT JOIN LATERAL (
	SELECT e.actor
	FROM /*:cms.prefix:*/events e
	WHERE e.revision = n.revision AND e.action IN ('created', 'saved', 'applied')
	ORDER BY e.event DESC
	LIMIT 1
) ea ON true
LEFT JOIN /*:cms.prefix:*/users ua ON ua.usr = ea.actor
LEFT JOIN /*:cms.prefix:*/drafts d ON d.node = n.node
LEFT JOIN /*:cms.prefix:*/revisions dr ON dr.revision = d.revision
LEFT JOIN /*:cms.prefix:*/users ud ON ud.usr = dr.editor
LEFT JOIN /*:cms.prefix:*/edits m ON m.node = n.node AND m.editor = :mine
WHERE n.deleted IS NULL
ORDER BY
	n.changed DESC,
	n.node DESC
LIMIT :limit;

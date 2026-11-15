-- Returns no row when the node already holds this state, so an unchanged
-- save records nothing. `moved` says the parent changed, `edited` that the
-- content or title did; `left` is the uid of the parent it left.
UPDATE
	/*:cms.prefix:*/nodes n
SET
	parent = :parent,
	locked = :locked,
	content = :content::jsonb,
	title = :title::jsonb
FROM
	(
		SELECT
			node,
			parent,
			content,
			title
		FROM
			/*:cms.prefix:*/nodes
		WHERE
			node = :node
	) o
WHERE
	n.node = o.node
	AND (n.parent, n.locked, n.content, n.title) IS DISTINCT FROM (
		:parent::bigint,
		:locked::boolean,
		:content::jsonb,
		:title::jsonb
	)

RETURNING
	o.parent IS DISTINCT FROM n.parent AS moved,
	(o.content, o.title) IS DISTINCT FROM (n.content, n.title) AS edited,
	(SELECT l.uid FROM /*:cms.prefix:*/nodes l WHERE l.node = o.parent) AS left;

-- Writes a node's publication and returns what it was before, so the
-- store records an event for each switch that moved. Returns no row when
-- nothing changed. A null value keeps the stored one.
WITH old AS (
	SELECT
		published,
		hidden
	FROM
		/*:cms.prefix:*/publications
	WHERE
		node = :node
)
INSERT INTO /*:cms.prefix:*/publications AS p (
	node,
	published,
	hidden
) VALUES (
	:node,
	coalesce(:published::boolean, false),
	coalesce(:hidden::boolean, false)
)
ON CONFLICT (node) DO UPDATE SET
	published = coalesce(:published::boolean, p.published),
	hidden = coalesce(:hidden::boolean, p.hidden)
WHERE
	(p.published, p.hidden) IS DISTINCT FROM (
		coalesce(:published::boolean, p.published),
		coalesce(:hidden::boolean, p.hidden)
	)

RETURNING
	(SELECT old.published FROM old) AS was_published,
	(SELECT old.hidden FROM old) AS was_hidden,
	p.published,
	p.hidden;

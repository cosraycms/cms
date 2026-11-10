-- Returns no row when the node already holds this state, so an unchanged
-- save writes no history row and leaves `changed` alone. The editor is
-- not compared: saving the same state again changes nothing.
UPDATE
	/*:cms.prefix:*/nodes
SET
	parent = :parent,
	published = :published,
	locked = :locked,
	hidden = :hidden,
	editor = :editor,
	content = :content::jsonb,
	title = :title::jsonb
WHERE
	node = :node
	AND (parent, published, locked, hidden, content, title) IS DISTINCT FROM (
		:parent::bigint,
		:published::boolean,
		:locked::boolean,
		:hidden::boolean,
		:content::jsonb,
		:title::jsonb
	)

RETURNING node;

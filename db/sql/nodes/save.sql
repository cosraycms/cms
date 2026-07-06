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

RETURNING node;

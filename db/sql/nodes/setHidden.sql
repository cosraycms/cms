UPDATE
	/*:cms.prefix:*/nodes
SET
	hidden = :hidden,
	editor = :editor
WHERE
	node = :node;

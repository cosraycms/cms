UPDATE
	/*:cms.prefix:*/nodes
SET
	hidden = :hidden,
	editor = :editor
WHERE
	node = :node
	AND hidden IS DISTINCT FROM :hidden::boolean;

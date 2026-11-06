-- Records a change kept outside the row, such as the handle or a URL path:
-- the triggers move `changed` and write a history row.
UPDATE
	/*:cms.prefix:*/nodes
SET
	editor = :editor
WHERE
	node = :node;

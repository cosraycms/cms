DELETE FROM
	/*:cms.prefix:*/edits
WHERE
	node = :node
	AND editor = :editor;

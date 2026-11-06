DELETE FROM
	/*:cms.prefix:*/autosaves
WHERE
	node = :node
	AND editor = :editor;

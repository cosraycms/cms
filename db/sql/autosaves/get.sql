SELECT
	changed,
	base,
	content,
	settings
FROM
	/*:cms.prefix:*/autosaves
WHERE
	node = :node
	AND editor = :editor;

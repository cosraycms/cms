SELECT
	changed,
	base,
	content,
	settings
FROM
	/*:cms.prefix:*/edits
WHERE
	node = :node
	AND editor = :editor;

UPDATE
	/*:cms.prefix:*/edits
SET
	content = :content::jsonb
WHERE
	node = :node
	AND editor = :editor;

UPDATE
	/*:cms.prefix:*/edits
SET
	base = :to
WHERE
	node = :node
	AND editor = :editor
	AND base = :from;

-- Moves an autosave's base along with a change that left the content alone,
-- but only when the edits started from the state that changed.
UPDATE
	/*:cms.prefix:*/autosaves
SET
	base = :to::timestamptz
WHERE
	node = :node
	AND editor = :editor
	AND base = :from::timestamptz;

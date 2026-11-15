-- Dates an action on the node: `changed` with every action that moves
-- it, `edited` and the editor only when the main version changed.
UPDATE
	/*:cms.prefix:*/nodes
SET
	changed = :at::timestamptz,
	edited = CASE WHEN :edited::boolean THEN :at::timestamptz ELSE edited END,
	editor = CASE WHEN :edited::boolean THEN :editor::bigint ELSE editor END
WHERE
	node = :node;

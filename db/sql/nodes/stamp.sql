-- Dates an action on the node: `changed` with every action that moves
-- it, `edited` only when the main version changed.
UPDATE
	/*:cms.prefix:*/nodes
SET
	changed = :at::timestamptz,
	edited = CASE WHEN :edited::boolean THEN :at::timestamptz ELSE edited END
WHERE
	node = :node;

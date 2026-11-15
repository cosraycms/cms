-- Replaces the manual order of one collection's top level with `uids`,
-- 1..n. Rows of entries missing from the list go, so those sort after the
-- positioned ones again.
DELETE FROM
	/*:cms.prefix:*/positions
WHERE
	collection = :collection;

INSERT INTO /*:cms.prefix:*/positions (node, collection, position)
SELECT
	n.node,
	:collection,
	o.position
FROM
	jsonb_array_elements_text(:uids::jsonb) WITH ORDINALITY AS o(uid, position)
	INNER JOIN /*:cms.prefix:*/nodes n ON n.uid = o.uid;

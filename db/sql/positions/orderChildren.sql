-- Replaces the manual order of one parent's children with `uids`, 1..n.
-- Rows of children missing from the list go, so those sort after the
-- positioned ones again; uids that are not children of the parent are
-- ignored.
DELETE FROM
	/*:cms.prefix:*/node_positions
WHERE
	parent = (SELECT node FROM /*:cms.prefix:*/nodes WHERE uid = :parent);

INSERT INTO /*:cms.prefix:*/node_positions (node, parent, position)
SELECT
	n.node,
	n.parent,
	o.position
FROM
	jsonb_array_elements_text(:uids::jsonb) WITH ORDINALITY AS o(uid, position)
	INNER JOIN /*:cms.prefix:*/nodes n ON n.uid = o.uid
	INNER JOIN /*:cms.prefix:*/nodes p ON p.node = n.parent
WHERE
	p.uid = :parent;

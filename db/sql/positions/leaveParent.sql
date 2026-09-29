-- A node that moved to another parent (or to the top level) loses its
-- place among its former siblings.
DELETE FROM
	/*:cms.prefix:*/node_positions
WHERE
	node = :node
	AND parent IS NOT NULL
	AND parent IS DISTINCT FROM :parent;

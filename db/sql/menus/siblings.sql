-- One sibling group in render order: position ties resolve by item id,
-- matching the recursive read query's ordering.
SELECT
	item
FROM
	/*:cms.prefix:*/menu_items
WHERE
	menu = :menu
	AND parent IS NOT DISTINCT FROM :parent
ORDER BY
	position,
	item;

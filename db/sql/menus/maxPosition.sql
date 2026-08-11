SELECT
	coalesce(max(position), 0) AS position
FROM
	/*:cms.prefix:*/menu_items
WHERE
	menu = :menu
	AND parent IS NOT DISTINCT FROM :parent;

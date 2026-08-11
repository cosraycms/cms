SELECT
	item,
	parent,
	menu,
	position
FROM
	/*:cms.prefix:*/menu_items
WHERE
	item = :item;

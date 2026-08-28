SELECT
	item,
	parent,
	menu,
	position,
	data
FROM
	/*:cms.prefix:*/menu_items
WHERE
	item = :item;

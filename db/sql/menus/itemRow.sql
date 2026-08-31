SELECT
	item,
	parent,
	menu,
	position,
	hidden,
	data
FROM
	/*:cms.prefix:*/menu_items
WHERE
	item = :item;

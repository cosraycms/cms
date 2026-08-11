UPDATE /*:cms.prefix:*/menu_items
SET
	parent = :parent,
	position = :position
WHERE
	item = :item;

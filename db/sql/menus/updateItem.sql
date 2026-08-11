UPDATE /*:cms.prefix:*/menu_items
SET
	data = :data
WHERE
	item = :item;

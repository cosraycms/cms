UPDATE /*:cms.prefix:*/menu_items
SET
	hidden = :hidden,
	data = :data
WHERE
	item = :item;

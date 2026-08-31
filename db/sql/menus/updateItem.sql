UPDATE /*:cms.prefix:*/menu_items
SET
	hidden = :hidden,
	data = :data,
	editor = :editor
WHERE
	item = :item;

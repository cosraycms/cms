DELETE FROM /*:cms.prefix:*/menu_items
WHERE
	menu = :menu
RETURNING item;

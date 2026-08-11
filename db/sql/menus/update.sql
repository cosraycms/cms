UPDATE /*:cms.prefix:*/menus
SET
	description = :description
WHERE
	menu = :menu;

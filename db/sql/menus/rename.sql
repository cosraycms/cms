-- The items follow through the menu FK's ON UPDATE CASCADE.
UPDATE /*:cms.prefix:*/menus
SET
	menu = :to
WHERE
	menu = :menu;

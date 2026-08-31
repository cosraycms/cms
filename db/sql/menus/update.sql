UPDATE /*:cms.prefix:*/menus
SET
	description = :description,
	max_depth = :maxDepth
WHERE
	menu = :menu;

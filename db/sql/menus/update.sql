UPDATE /*:cms.prefix:*/menus
SET
	description = :description,
	max_depth = :maxDepth,
	editor = :editor
WHERE
	menu = :menu;

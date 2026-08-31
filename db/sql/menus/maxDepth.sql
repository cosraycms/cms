SELECT
	max_depth AS "maxDepth"
FROM
	/*:cms.prefix:*/menus
WHERE
	menu = :menu;

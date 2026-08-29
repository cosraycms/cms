SELECT
	m.menu,
	m.description,
	count(i.item) AS items
FROM
	/*:cms.prefix:*/menus m
LEFT JOIN
	/*:cms.prefix:*/menu_items i ON i.menu = m.menu
GROUP BY
	m.menu,
	m.description
ORDER BY
	m.description,
	m.menu;

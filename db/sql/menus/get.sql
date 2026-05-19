WITH RECURSIVE nav AS (
   SELECT
	   menu,
	   item AS path,
	   array[position] AS sort,
	   1 AS level,
	   item,
	   parent,
	   data
   FROM
	   cms.menu_items
   WHERE
	   parent IS NULL
	   AND menu = :menu

   UNION ALL

   SELECT
	   m.menu,
	   path || '.' || m.item AS path,
	   sort || m.position AS sort,
	   nav.level + 1 AS level,
	   m.item,
	   m.parent,
	   m.data
   FROM
	   cms.menu_items m
   JOIN
		   nav ON m.parent = nav.item
)
SELECT
	menu,
	item,
	sort,
	path,
	parent,
	level,
	data
FROM
	nav
ORDER BY
	menu,
	sort,
	item;
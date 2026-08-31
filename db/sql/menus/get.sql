WITH RECURSIVE nav AS (
   SELECT
	   menu,
	   item AS path,
	   array[position] AS sort,
	   1 AS level,
	   item,
	   parent,
	   hidden,
	   data
   FROM
	   /*:cms.prefix:*/menu_items
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
	   m.hidden,
	   m.data
   FROM
	   /*:cms.prefix:*/menu_items m
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
	hidden,
	data,
	-- Node items that store the linked node's uid resolve their target's
	-- current localized paths at read time; legacy rows carry a numeric
	-- stub instead and fall back to the snapshot in `data`.
	CASE WHEN nav.data->>'type' = 'node' AND jsonb_typeof(nav.data->'node') = 'string' THEN (
		SELECT
			jsonb_object_agg(up.locale, up.path)
		FROM
			/*:cms.prefix:*/url_paths up
		JOIN
			/*:cms.prefix:*/nodes n ON n.node = up.node
		WHERE
			n.uid = nav.data->>'node'
			AND up.inactive IS NULL
	) END AS node_paths,
	-- Node items without a stored title inherit the node's materialized
	-- title map; deleted nodes leave it null so the snapshot wins.
	CASE WHEN nav.data->>'type' = 'node' AND jsonb_typeof(nav.data->'node') = 'string' THEN (
		SELECT
			n.title
		FROM
			/*:cms.prefix:*/nodes n
		WHERE
			n.uid = nav.data->>'node'
			AND n.deleted IS NULL
	) END AS node_title
FROM
	nav
ORDER BY
	menu,
	sort,
	item;

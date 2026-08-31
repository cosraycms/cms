-- Rebuild source: menu items linking a node — `node` items and the dynamic
-- `children` items, both of which store the target's uid under `node`.
-- Legacy rows carry a numeric stub there instead and are skipped.
SELECT
	item,
	data ->> 'node' AS uid
FROM
	/*:cms.prefix:*/menu_items
WHERE
	data ->> 'type' IN ('node', 'children')
	AND jsonb_typeof(data -> 'node') = 'string'
	AND data ->> 'node' <> ''
ORDER BY
	item;

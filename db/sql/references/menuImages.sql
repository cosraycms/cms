-- Rebuild source: menu items carrying an asset uid. Menus have no
-- cosray write path yet, so menu references only enter via rebuild.
SELECT
	item,
	data ->> 'image' AS uid
FROM
	/*:cms.prefix:*/menu_items
WHERE
	data ->> 'image' IS NOT NULL
	AND data ->> 'image' <> ''
ORDER BY
	item;

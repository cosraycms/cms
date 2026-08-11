-- Rebuild source: menu items carrying asset uids — `image` icons and
-- `asset` link targets. The menu write API keeps the index in step;
-- the rebuild covers restores and manual data changes.
SELECT
	item,
	uid
FROM
	/*:cms.prefix:*/menu_items,
	LATERAL (VALUES (data ->> 'image'), (data ->> 'asset')) AS refs (uid)
WHERE
	uid IS NOT NULL
	AND uid <> ''
ORDER BY
	item;

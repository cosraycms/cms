-- How deep the menu's tree currently reaches; 0 for an empty menu. Guards
-- `max_depth` against being set shallower than the tree already is, which
-- would leave the limit inert instead of enforced.
WITH RECURSIVE tree AS (
	SELECT
		item,
		1 AS level
	FROM
		/*:cms.prefix:*/menu_items
	WHERE
		menu = :menu
		AND parent IS NULL

	UNION ALL

	SELECT
		m.item,
		t.level + 1
	FROM
		/*:cms.prefix:*/menu_items m
	JOIN
		tree t ON m.parent = t.item
)
SELECT
	coalesce(max(level), 0) AS height
FROM
	tree;

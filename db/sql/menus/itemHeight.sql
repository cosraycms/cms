-- How tall the subtree rooted at this item is: 1 for a leaf, 2 for an item
-- with children, and so on. Moving that item puts its deepest descendant
-- `height` levels below the target parent.
WITH RECURSIVE subtree AS (
	SELECT
		item,
		1 AS level
	FROM
		/*:cms.prefix:*/menu_items
	WHERE
		item = :item

	UNION ALL

	SELECT
		m.item,
		s.level + 1
	FROM
		/*:cms.prefix:*/menu_items m
	JOIN
		subtree s ON m.parent = s.item
)
SELECT
	coalesce(max(level), 0) AS height
FROM
	subtree;

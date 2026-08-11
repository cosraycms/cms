WITH RECURSIVE tree AS (
	SELECT
		item
	FROM
		/*:cms.prefix:*/menu_items
	WHERE
		item = :item

	UNION ALL

	SELECT
		m.item
	FROM
		/*:cms.prefix:*/menu_items m
	JOIN
		tree t ON m.parent = t.item
)
DELETE FROM /*:cms.prefix:*/menu_items
WHERE
	item IN (SELECT item FROM tree);

WITH RECURSIVE ancestors AS (
	SELECT
		item,
		parent
	FROM
		/*:cms.prefix:*/menu_items
	WHERE
		item = :item

	UNION ALL

	SELECT
		m.item,
		m.parent
	FROM
		/*:cms.prefix:*/menu_items m
	JOIN
		ancestors a ON m.item = a.parent
)
SELECT
	item
FROM
	ancestors;

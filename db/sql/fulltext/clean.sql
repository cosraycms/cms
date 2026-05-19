DELETE FROM cms.full_text ft
WHERE
	ft.node NOT IN (
		SELECT
			n.node
		FROM
			cms.nodes n
		WHERE
			n.deleted IS NULL
			AND n.published = true
	);
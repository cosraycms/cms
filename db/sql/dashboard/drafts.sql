SELECT
	count(*) AS total,
	count(*) FILTER (WHERE changed >= now() - interval '7 days') AS recent
FROM /*:cms.prefix:*/nodes
WHERE deleted IS NULL
	AND published = false;

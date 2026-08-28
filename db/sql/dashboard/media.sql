SELECT
	count(*) AS total,
	coalesce(sum(bytes), 0) AS bytes
FROM /*:cms.prefix:*/assets;

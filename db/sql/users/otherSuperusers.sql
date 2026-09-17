SELECT
	count(*) AS total
FROM
	/*:cms.prefix:*/users
WHERE
	deleted IS NULL
	AND active
	AND 'superuser' = ANY(roles)
	AND usr != :usr;

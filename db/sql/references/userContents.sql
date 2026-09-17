-- Rebuild source: the field content of every live user.
SELECT
	uid,
	(data -> 'content')::text AS content
FROM
	/*:cms.prefix:*/users
WHERE
	deleted IS NULL
	AND data ? 'content'
ORDER BY
	usr;

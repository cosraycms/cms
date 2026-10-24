SELECT
	uid,
	name,
	created,
	expires,
	used
FROM
	/*:cms.prefix:*/auth_tokens
WHERE
	usr = :usr
ORDER BY
	created, uid;

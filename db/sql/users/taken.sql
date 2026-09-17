SELECT
	COALESCE(bool_or(lower(email) = lower(:email)), false) AS email,
	COALESCE(bool_or(lower(username) = lower(:username)), false) AS username
FROM
	/*:cms.prefix:*/users
WHERE
	deleted IS NULL
	AND usr != :usr;

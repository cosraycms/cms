SELECT
	node
FROM
	/*:cms.prefix:*/nodes
WHERE
	uid = :uid
	AND deleted IS NULL
LIMIT 1;

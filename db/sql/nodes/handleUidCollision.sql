SELECT
	node
FROM
	/*:cms.prefix:*/nodes
WHERE
	uid = :handle
	AND node <> :node
LIMIT 1;

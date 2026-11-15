DELETE FROM
	/*:cms.prefix:*/handles
WHERE
	node = :node

RETURNING node;

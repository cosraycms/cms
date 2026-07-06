DELETE FROM
	/*:cms.prefix:*/asset_references
WHERE
	owner_type = :ownerType
	AND owner_uid = :ownerUid;

DELETE FROM
	/*:cms.prefix:*/node_references
WHERE
	owner_type = :ownerType
	AND owner_uid = :ownerUid;

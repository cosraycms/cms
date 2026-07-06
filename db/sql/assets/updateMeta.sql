UPDATE
	/*:cms.prefix:*/assets
SET
	meta = :meta::jsonb
WHERE
	uid = :uid;

SELECT
	asset,
	uid,
	disk,
	permission,
	key,
	filename,
	mime,
	bytes,
	width,
	height,
	hash,
	meta,
	created,
	changed
FROM
	/*:cms.prefix:*/assets
WHERE
	hash = :hash
	AND disk = :disk
	AND permission = :permission
ORDER BY
	asset
LIMIT 1;

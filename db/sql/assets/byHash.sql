SELECT
	asset,
	uid,
	disk,
	key,
	filename,
	mime,
	bytes,
	width,
	height,
	kind,
	hash,
	meta,
	created,
	changed
FROM
	/*:cms.prefix:*/assets
WHERE
	hash = :hash
	AND disk = :disk
ORDER BY
	asset
LIMIT 1;

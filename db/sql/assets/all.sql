SELECT
	uid,
	disk,
	key,
	filename,
	mime,
	bytes,
	width,
	height,
	hash,
	meta
FROM
	/*:cms.prefix:*/assets
ORDER BY
	asset;

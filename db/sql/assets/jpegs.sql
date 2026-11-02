SELECT
	uid,
	key,
	width,
	height
FROM
	/*:cms.prefix:*/assets
WHERE
	mime = 'image/jpeg';

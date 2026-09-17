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
	hash,
	meta,
	created,
	changed
FROM
	/*:cms.prefix:*/assets
WHERE
	uid IN (SELECT jsonb_array_elements_text(:uids::jsonb));

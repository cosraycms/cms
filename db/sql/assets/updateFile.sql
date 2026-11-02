UPDATE
	/*:cms.prefix:*/assets
SET
	key = :key,
	filename = :filename,
	mime = :mime,
	bytes = :bytes,
	width = :width,
	height = :height,
	hash = :hash,
	changed = now()
WHERE
	uid = :uid;

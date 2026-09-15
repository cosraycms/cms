INSERT INTO /*:cms.prefix:*/assets (
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
	creator
) VALUES (
	:uid,
	:disk,
	:permission,
	:key,
	:filename,
	:mime,
	:bytes,
	:width,
	:height,
	:hash,
	:meta,
	:creator
)
RETURNING asset;

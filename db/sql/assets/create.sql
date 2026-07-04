INSERT INTO /*:cms.prefix:*/assets (
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
	creator
) VALUES (
	:uid,
	:disk,
	:key,
	:filename,
	:mime,
	:bytes,
	:width,
	:height,
	:kind,
	:hash,
	:meta,
	:creator
)
RETURNING asset;

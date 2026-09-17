-- Creates an active superuser owned by the seeded system user
-- (uid 0000000000000).
INSERT INTO /*:cms.prefix:*/users (
	uid,
	email,
	password,
	roles,
	active,
	data,
	creator,
	editor
)
SELECT
	:uid,
	:email,
	:password,
	ARRAY['superuser'],
	true,
	:data,
	usr,
	usr
FROM
	/*:cms.prefix:*/users
WHERE
	uid = '0000000000000';

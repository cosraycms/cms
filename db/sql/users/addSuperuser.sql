-- Creates an active superuser owned by the seeded system user
-- (uid 0000000000000).
INSERT INTO /*:cms.prefix:*/users (
	uid,
	email,
	password,
	rolename,
	active,
	data,
	creator,
	editor
)
SELECT
	:uid,
	:email,
	:password,
	'superuser',
	true,
	:data,
	usr,
	usr
FROM
	/*:cms.prefix:*/users
WHERE
	uid = '0000000000000';

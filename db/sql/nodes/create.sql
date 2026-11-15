INSERT INTO /*:cms.prefix:*/nodes (
	uid,
	parent,
	type,
	locked,
	creator,
	created,
	changed,
	edited,
	content,
	title
)
SELECT
	:uid,
	:parent,
	type,
	:locked,
	:creator,
	COALESCE(CAST(:created AS timestamptz), now()),
	COALESCE(CAST(:changed AS timestamptz), now()),
	COALESCE(CAST(:changed AS timestamptz), now()),
	:content,
	:title
FROM
	/*:cms.prefix:*/types t
WHERE
	t.handle = :type

ON CONFLICT (uid) DO NOTHING

RETURNING node, created, changed;

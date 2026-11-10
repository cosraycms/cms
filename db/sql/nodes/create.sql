INSERT INTO /*:cms.prefix:*/nodes (
	uid,
	parent,
	type,
	published,
	locked,
	hidden,
	editor,
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
	:published,
	:locked,
	:hidden,
	:editor,
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

RETURNING node;

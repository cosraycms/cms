INSERT INTO /*:cms.prefix:*/nodes (
	uid,
	parent,
	type,
	published,
	locked,
	hidden,
	editor,
	creator,
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
	:editor,
	:content,
	:title
FROM
	/*:cms.prefix:*/types t
WHERE
	t.handle = :type

ON CONFLICT (uid) DO NOTHING

RETURNING node;

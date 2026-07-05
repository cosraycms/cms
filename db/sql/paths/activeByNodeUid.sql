SELECT
	up.path,
	up.locale
FROM
	/*:cms.prefix:*/url_paths up
	JOIN /*:cms.prefix:*/nodes n ON n.node = up.node
WHERE
	n.uid = :uid
	AND up.inactive IS NULL;

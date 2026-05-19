SELECT
	up.path,
	up.locale
FROM
	cms.url_paths up
WHERE
	up.node = :node
	AND up.inactive IS NULL;
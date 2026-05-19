SELECT
	up.node,
	up.path,
	up.locale,
	up.creator,
	up.inactive,
	up.created
FROM
	cms.url_paths up
WHERE
	up.path = :path;
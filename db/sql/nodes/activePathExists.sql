SELECT
	true
FROM
	/*:cms.prefix:*/url_paths
WHERE
	path = :path
	AND inactive IS NULL;

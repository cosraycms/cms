DELETE FROM cms.url_paths
WHERE
	path = :path
	AND inactive IS NOT NULL;
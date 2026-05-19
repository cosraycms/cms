SELECT
	path,
	locale,
	creator,
	editor,
	created,
	inactive
FROM
	cms.url_paths
WHERE
	node = :node
	AND inactive IS NULL;
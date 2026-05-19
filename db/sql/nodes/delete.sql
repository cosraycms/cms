UPDATE
	cms.nodes
SET
	deleted = now()
WHERE
	uid = :uid;

UPDATE
	cms.url_paths
SET
	inactive = now(),
	editor = :editor
WHERE node IN (
	SELECT n.node FROM cms.nodes n WHERE n.uid = :uid
);
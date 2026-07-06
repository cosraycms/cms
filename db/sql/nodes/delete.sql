UPDATE
	/*:cms.prefix:*/nodes
SET
	deleted = now()
WHERE
	uid = :uid;

UPDATE
	/*:cms.prefix:*/url_paths
SET
	inactive = now(),
	editor = :editor
WHERE node IN (
	SELECT n.node FROM /*:cms.prefix:*/nodes n WHERE n.uid = :uid
);

-- Soft-deleted nodes hold no references: their derived index rows go
-- with them, so they never block an asset deletion.
DELETE FROM
	/*:cms.prefix:*/asset_references
WHERE
	owner_type = 'node'
	AND owner_uid = :uid;

DELETE FROM
	/*:cms.prefix:*/node_references
WHERE
	owner_type = 'node'
	AND owner_uid = :uid;
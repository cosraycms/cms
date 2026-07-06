-- Rebuild source: content of every live node. Soft-deleted nodes hold
-- no references (their rows are removed on delete).
SELECT
	uid,
	content::text AS content
FROM
	/*:cms.prefix:*/nodes
WHERE
	deleted IS NULL
ORDER BY
	node;

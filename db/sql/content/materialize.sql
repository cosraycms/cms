-- Gives every node the content of its main revision again.
UPDATE
	/*:cms.prefix:*/nodes n
SET
	content = r.content
FROM
	/*:cms.prefix:*/revisions r
WHERE
	r.revision = n.revision
	AND n.content IS DISTINCT FROM r.content;

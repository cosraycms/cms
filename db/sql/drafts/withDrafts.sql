-- The uids among the given ones whose node has a working copy.
SELECT
	n.uid
FROM
	/*:cms.prefix:*/drafts d
	INNER JOIN /*:cms.prefix:*/nodes n ON n.node = d.node
WHERE
	n.uid IN (SELECT jsonb_array_elements_text(:uids::jsonb));

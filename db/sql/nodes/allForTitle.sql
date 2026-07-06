-- All live nodes with their type handle and content, for the title rebuild.
SELECT
	n.uid,
	t.handle AS type_handle,
	n.content
FROM
	/*:cms.prefix:*/nodes n
	INNER JOIN /*:cms.prefix:*/types t USING(type)
WHERE
	n.deleted IS NULL;

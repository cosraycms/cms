SELECT count(*) AS total
FROM /*:cms.prefix:*/nodes
WHERE deleted IS NULL;

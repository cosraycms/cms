SELECT n.node, n.uid, n.content, n.title, n.deleted, t.handle
FROM /*:cms.prefix:*/nodes n JOIN /*:cms.prefix:*/types t ON t.type = n.type
WHERE n.node = :node
FOR UPDATE OF n;

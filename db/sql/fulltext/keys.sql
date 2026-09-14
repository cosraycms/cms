SELECT node, uid FROM /*:cms.prefix:*/nodes
WHERE node > :after AND node <= :ceiling
ORDER BY node LIMIT 100;

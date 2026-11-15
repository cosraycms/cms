SELECT n.*, p.published, p.hidden, (SELECT count(*) FROM cms.events e WHERE e.node = n.node) AS events
FROM cms.nodes n
LEFT JOIN cms.publications p ON p.node = n.node
WHERE n.uid = :uid;

SELECT n.*, (SELECT count(*) FROM cms.nodes_history h WHERE h.node = n.node) AS history
FROM cms.nodes n WHERE uid = :uid;

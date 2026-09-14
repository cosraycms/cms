DELETE FROM cms.url_paths WHERE node = (SELECT node FROM cms.nodes WHERE uid = :uid);

UPDATE cms.url_paths SET inactive = clock_timestamp() WHERE node = (SELECT node FROM cms.nodes WHERE uid = :uid);

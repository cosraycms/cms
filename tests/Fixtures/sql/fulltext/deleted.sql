UPDATE cms.nodes SET deleted = clock_timestamp() WHERE uid = :uid;

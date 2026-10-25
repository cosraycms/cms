SELECT pg_advisory_xact_lock(hashtextextended('cosray.node:' || :uid, 0));

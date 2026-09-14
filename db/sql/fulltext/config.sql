SELECT format('%I.%I', n.nspname, c.cfgname) AS config
FROM pg_ts_config c
JOIN pg_namespace n ON n.oid = c.cfgnamespace
WHERE c.cfgnamespace = (SELECT relnamespace FROM pg_class WHERE oid = '/*:cms.prefix:*/nodes'::regclass)
	AND c.cfgname = '/*:cms.obj:*/fts_' || :name;

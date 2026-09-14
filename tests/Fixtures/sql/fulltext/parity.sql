SELECT c.cfgname,
	to_tsvector(format('cms.%I', c.cfgname)::regconfig, 'Café running Häuser')::text AS updated,
	to_tsvector(format('fts_install.%I', c.cfgname)::regconfig, 'Café running Häuser')::text AS installed,
	ts_headline(format('cms.%I', c.cfgname)::regconfig, 'Café running Häuser', websearch_to_tsquery(format('cms.%I', c.cfgname)::regconfig, 'cafe')) AS updated_headline,
	ts_headline(format('fts_install.%I', c.cfgname)::regconfig, 'Café running Häuser', websearch_to_tsquery(format('fts_install.%I', c.cfgname)::regconfig, 'cafe')) AS installed_headline
FROM pg_ts_config c JOIN pg_namespace n ON c.cfgnamespace = n.oid WHERE n.nspname = 'cms';

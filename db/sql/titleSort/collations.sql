SELECT collname AS name,
	quote_ident(n.nspname) || '.' || quote_ident(c.collname) AS identifier
FROM pg_collation c
JOIN pg_namespace n ON n.oid = c.collnamespace
WHERE n.nspname = 'pg_catalog' AND c.collprovider = 'i';

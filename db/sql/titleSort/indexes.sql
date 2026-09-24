SELECT CASE WHEN starts_with(i.relname, '/*:cms.obj:*/ix_nodes_title_')
		THEN substring(i.relname FROM length('/*:cms.obj:*/') + 1)
		ELSE 'legacy:' || i.relname
	END AS name,
	quote_ident(n.nspname) || '.' || quote_ident(i.relname) AS identifier,
	x.indisvalid AS valid,
	pg_get_indexdef(i.oid) AS definition,
	obj_description(i.oid, 'pg_class') AS signature
FROM pg_index x
JOIN pg_class i ON i.oid = x.indexrelid
JOIN pg_namespace n ON n.oid = i.relnamespace
WHERE x.indrelid = '/*:cms.prefix:*/nodes'::regclass
	AND (starts_with(i.relname, '/*:cms.obj:*/ix_nodes_title_')
		OR starts_with(i.relname, 'ix_nodes_title_'));

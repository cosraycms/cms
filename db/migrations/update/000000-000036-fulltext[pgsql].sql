ALTER TABLE /*:cms.prefix:*/full_text ADD COLUMN source text NOT NULL DEFAULT '';

DO $$
DECLARE
	config record;
	mapping record;
	target_schema text;
	accent_schema text;
	target_name text;
BEGIN
	SELECT n.nspname INTO target_schema
	FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
	WHERE c.oid = '/*:cms.prefix:*/nodes'::regclass;
	SELECT n.nspname INTO STRICT accent_schema
	FROM pg_extension e JOIN pg_namespace n ON n.oid = e.extnamespace
	WHERE e.extname = 'unaccent';

	FOR config IN SELECT c.* FROM pg_ts_config c
		JOIN pg_namespace n ON n.oid = c.cfgnamespace WHERE n.nspname = 'pg_catalog'
	LOOP
		target_name := '/*:cms.obj:*/fts_' || config.cfgname;
		EXECUTE format('CREATE TEXT SEARCH CONFIGURATION %I.%I (COPY = pg_catalog.%I)',
			target_schema, target_name, config.cfgname);
		FOR mapping IN
			SELECT t.alias, string_agg(format('%I.%I', n.nspname, d.dictname), ', ' ORDER BY m.mapseqno) AS dictionaries
			FROM pg_ts_config_map m
			JOIN pg_ts_dict d ON d.oid = m.mapdict
			JOIN pg_namespace n ON n.oid = d.dictnamespace
			JOIN ts_token_type(config.cfgparser) t ON t.tokid = m.maptokentype
			WHERE m.mapcfg = config.oid GROUP BY t.alias
		LOOP
			EXECUTE format('ALTER TEXT SEARCH CONFIGURATION %I.%I ALTER MAPPING FOR %I WITH %I.unaccent, %s',
				target_schema, target_name, mapping.alias, accent_schema, mapping.dictionaries);
		END LOOP;
	END LOOP;
END;
$$;

CREATE FUNCTION /*:cms.prefix:*/fulltext_vector(config regconfig, contributions jsonb, uid text)
	RETURNS tsvector LANGUAGE plpgsql IMMUTABLE STRICT PARALLEL SAFE AS $$
DECLARE
	part jsonb;
	document tsvector := ''::tsvector;
BEGIN
	FOR part IN SELECT value FROM jsonb_array_elements(contributions)
	LOOP
		BEGIN
			document := document || setweight(to_tsvector(config, part->>'text'), (part->>'weight')::"char");
		EXCEPTION WHEN program_limit_exceeded THEN
			RAISE EXCEPTION 'Fulltext document for node % exceeds PostgreSQL limits at field %', uid, part->>'field'
				USING ERRCODE = '54000';
		END;
	END LOOP;
	RETURN document;
END;
$$;

-- pg_trgm is a trusted extension, so a database owner can create it.
CREATE EXTENSION IF NOT EXISTS pg_trgm;

DO $$
DECLARE
	accent_schema text;
	trigram_schema text;
BEGIN
	SELECT n.nspname INTO STRICT accent_schema
	FROM pg_extension e JOIN pg_namespace n ON n.oid = e.extnamespace
	WHERE e.extname = 'unaccent';
	SELECT n.nspname INTO STRICT trigram_schema
	FROM pg_extension e JOIN pg_namespace n ON n.oid = e.extnamespace
	WHERE e.extname = 'pg_trgm';

	-- unaccent() is only stable, but an index expression must be immutable.
	-- Naming the dictionary pins the result.
	EXECUTE format(
		'CREATE FUNCTION /*:cms.prefix:*/fulltext_fold(value text) RETURNS text '
			|| 'LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE '
			|| 'AS $f$ SELECT lower(%I.unaccent(%L::regdictionary, value)) $f$',
		accent_schema, format('%I.unaccent', accent_schema));
	EXECUTE format(
		'CREATE INDEX /*:cms.obj:*/ix_full_text_trgm ON /*:cms.prefix:*/full_text '
			|| 'USING GIN (/*:cms.prefix:*/fulltext_fold(source) %I.gin_trgm_ops)',
		trigram_schema);
END;
$$;

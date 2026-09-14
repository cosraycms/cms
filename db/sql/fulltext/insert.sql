INSERT INTO /*:cms.prefix:*/full_text (node, locale, document, source)
SELECT :node, locale, /*:cms.prefix:*/fulltext_vector(config::regconfig, contributions, :uid), source
FROM jsonb_to_recordset(:documents::jsonb) AS d(locale text, config text, contributions jsonb, source text)
WHERE EXISTS (SELECT FROM /*:cms.prefix:*/nodes WHERE node = :node AND deleted IS NULL)
RETURNING locale;

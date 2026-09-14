SELECT locale, document::text, source FROM cms.full_text WHERE node = :node ORDER BY locale;

SELECT f.locale, ts_rank_cd(f.document, q.query) AS score,
	ts_headline(:config::regconfig, f.source, q.query,
		'StartSel=' || chr(1) || ', StopSel=' || chr(2) || ', MaxWords=35, MinWords=15, MaxFragments=2, FragmentDelimiter= … ') AS headline
FROM cms.full_text f
CROSS JOIN (SELECT websearch_to_tsquery(:config::regconfig, :query) AS query) q
WHERE f.node = :node AND f.locale = :locale AND f.document @@ q.query;

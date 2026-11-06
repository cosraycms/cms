DELETE FROM
	/*:cms.prefix:*/autosaves
WHERE
	changed < now() - make_interval(days => :days);

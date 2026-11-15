DELETE FROM
	/*:cms.prefix:*/edits
WHERE
	changed < now() - make_interval(days => :days);

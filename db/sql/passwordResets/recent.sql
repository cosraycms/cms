SELECT EXISTS (
	SELECT 1
	FROM /*:cms.prefix:*/password_resets
	WHERE usr = :usr AND created > now() - make_interval(secs => :interval)
) AS recent;

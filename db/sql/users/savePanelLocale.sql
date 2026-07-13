UPDATE /*:cms.prefix:*/users
SET
	panel_locale = :locale,
	editor = :usr
WHERE
	usr = :usr;

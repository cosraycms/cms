UPDATE /*:cms.prefix:*/users
SET
	deleted = now(),
	active = false,
	editor = :editor
WHERE
	usr = :usr;

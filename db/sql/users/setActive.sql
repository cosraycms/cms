UPDATE /*:cms.prefix:*/users
SET
	active = :active,
	editor = :editor
WHERE
	usr = :usr;

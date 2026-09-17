UPDATE /*:cms.prefix:*/users
SET
	password = :password,
	editor = :editor
WHERE
	usr = :usr;

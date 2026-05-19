UPDATE cms.users
SET
	email = :email,
	username = :username,
	data = :data,
	editor = :editor,
	password = :password
WHERE
	usr = :usr;

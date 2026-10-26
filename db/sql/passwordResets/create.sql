INSERT INTO /*:cms.prefix:*/password_resets (
	token,
	usr,
	expires
) VALUES (
	:token,
	:usr,
	now() + make_interval(secs => :lifetime)
)
ON CONFLICT (usr) DO UPDATE SET
	token = EXCLUDED.token,
	created = EXCLUDED.created,
	expires = EXCLUDED.expires;

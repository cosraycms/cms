INSERT INTO /*:cms.prefix:*/password_resets AS r (
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
	expires = EXCLUDED.expires
WHERE r.created <= now() - make_interval(secs => :interval)
RETURNING usr;

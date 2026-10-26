INSERT INTO /*:cms.prefix:*/rate_limits AS r (
	bucket,
	hits,
	ends
) VALUES (
	:bucket,
	1,
	now() + make_interval(secs => :window)
)
ON CONFLICT (bucket) DO UPDATE SET
	hits = CASE WHEN r.ends <= now() THEN 1 ELSE r.hits + 1 END,
	ends = CASE WHEN r.ends <= now() THEN EXCLUDED.ends ELSE r.ends END
RETURNING
	hits,
	ceil(extract(epoch FROM ends - now()))::integer AS seconds;

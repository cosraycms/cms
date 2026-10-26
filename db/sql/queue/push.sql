INSERT INTO /*:cms.prefix:*/jobs (
	queue,
	job,
	payload,
	due
) VALUES (
	:queue,
	:job,
	CAST(:payload AS json),
	now() + make_interval(secs => :delay)
)
RETURNING id;

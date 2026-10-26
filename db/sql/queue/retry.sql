UPDATE /*:cms.prefix:*/jobs SET
	state = 'pending',
	due = now() + make_interval(secs => :delay),
	error = :error
WHERE id = :id;

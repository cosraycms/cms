UPDATE /*:cms.prefix:*/jobs SET
	state = 'failed',
	error = :error,
	failed = now()
WHERE id = :id;

SELECT
	id,
	queue,
	job,
	attempts,
	error,
	created,
	failed
FROM
	/*:cms.prefix:*/jobs
WHERE
	state = 'failed'
ORDER BY
	failed, id;

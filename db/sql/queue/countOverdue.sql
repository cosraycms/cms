SELECT
	count(*) AS total
FROM
	/*:cms.prefix:*/jobs
WHERE
	state <> 'failed'
	AND due < :before;

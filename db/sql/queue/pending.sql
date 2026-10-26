SELECT
	payload::text AS payload
FROM
	/*:cms.prefix:*/jobs
WHERE
	job = :job AND state <> 'failed'
ORDER BY
	id;

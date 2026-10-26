-- The job queue. A pending job runs once `due` has passed. A running job
-- holds a lease until `due`; once that passes, the runner is presumed dead
-- and the job is due again. Finished jobs are deleted; failed ones stay
-- until they are retried or pruned.

CREATE TABLE /*:cms.prefix:*/jobs (
	id bigint GENERATED ALWAYS AS IDENTITY,
	queue text NOT NULL DEFAULT 'default',
	job text NOT NULL,
	payload json NOT NULL DEFAULT '{}',
	state text NOT NULL DEFAULT 'pending',
	attempts integer NOT NULL DEFAULT 0,
	max_attempts integer NOT NULL DEFAULT 6,
	due timestamp with time zone NOT NULL DEFAULT now(),
	error text,
	created timestamp with time zone NOT NULL DEFAULT now(),
	failed timestamp with time zone,
	CONSTRAINT /*:cms.obj:*/pk_jobs PRIMARY KEY (id),
	CONSTRAINT /*:cms.obj:*/ck_jobs_queue CHECK (char_length(queue) BETWEEN 1 AND 64),
	CONSTRAINT /*:cms.obj:*/ck_jobs_job CHECK (char_length(job) BETWEEN 1 AND 255),
	CONSTRAINT /*:cms.obj:*/ck_jobs_state CHECK (state IN ('pending', 'running', 'failed')),
	CONSTRAINT /*:cms.obj:*/ck_jobs_attempts CHECK (attempts >= 0 AND max_attempts >= 1),
	CONSTRAINT /*:cms.obj:*/ck_jobs_error CHECK (char_length(error) <= 4000)
);
CREATE INDEX /*:cms.obj:*/ix_jobs_due ON /*:cms.prefix:*/jobs (due) WHERE state <> 'failed';

-- Wakes `queue:listen` when jobs are pushed. PostgreSQL delivers the
-- notification once the pushing transaction commits, and only to
-- sessions listening then; a runner that missed one finds the job anyway.
-- The channel is the same for every schema: a runner woken by another
-- site's jobs finds none of its own and waits again.

CREATE FUNCTION /*:cms.prefix:*/notify_jobs()
	RETURNS TRIGGER AS $$
BEGIN
	PERFORM pg_notify('cosray_jobs', '');
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER /*:cms.obj:*/jobs_trigger_01_notify AFTER INSERT ON /*:cms.prefix:*/jobs
	FOR EACH STATEMENT EXECUTE FUNCTION /*:cms.prefix:*/notify_jobs();

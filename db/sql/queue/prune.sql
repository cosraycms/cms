DELETE FROM /*:cms.prefix:*/jobs
WHERE state = 'failed' AND failed < now() - make_interval(days => :days);

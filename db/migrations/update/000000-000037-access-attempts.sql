CREATE TABLE /*:cms.prefix:*/access_attempts (
	permission text NOT NULL,
	client text NOT NULL,
	attempts integer NOT NULL,
	expires timestamptz NOT NULL,
	PRIMARY KEY (permission, client)
);
CREATE INDEX ON /*:cms.prefix:*/access_attempts (expires);

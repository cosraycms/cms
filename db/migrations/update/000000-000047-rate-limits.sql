-- Counters of the rate limiter, by an HMAC of their bucket. A counter
-- counts the hits of a window that ends at `ends`; the next hit after
-- that starts a new one. Unlogged: losing counters in a crash is fine.

CREATE UNLOGGED TABLE /*:cms.prefix:*/rate_limits (
	bucket text NOT NULL,
	hits integer NOT NULL,
	ends timestamp with time zone NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_rate_limits PRIMARY KEY (bucket),
	CONSTRAINT /*:cms.obj:*/ck_rate_limits_bucket CHECK (char_length(bucket) <= 128)
);
CREATE INDEX /*:cms.obj:*/ix_rate_limits_ends ON /*:cms.prefix:*/rate_limits (ends);

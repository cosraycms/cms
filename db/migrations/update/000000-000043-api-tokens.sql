-- API tokens: a user holds any number of named tokens, each with an
-- optional expiry and the time of its last use. Only the sha256 of a
-- token is stored; `uid` names it in listings and revocations. Tokens are
-- never edited after creation, and recording a use must not count as a
-- change, so the change trigger goes.

DROP TRIGGER /*:cms.obj:*/auth_tokens_trigger_01_change ON /*:cms.prefix:*/auth_tokens;

ALTER TABLE /*:cms.prefix:*/auth_tokens
	DROP CONSTRAINT /*:cms.obj:*/uc_auth_tokens_usr,
	ADD COLUMN uid text,
	ADD COLUMN name text NOT NULL DEFAULT '',
	ADD COLUMN expires timestamp with time zone,
	ADD COLUMN used timestamp with time zone;

-- The correlated WHERE makes PostgreSQL draw a new uid for every row.
UPDATE /*:cms.prefix:*/auth_tokens t SET uid = (
	SELECT string_agg(substr('123456789bcdfghklmnpqrstvwxyz', 1 + floor(random() * 29)::int, 1), '')
	FROM generate_series(1, 13)
	WHERE t.token IS NOT NULL
);

ALTER TABLE /*:cms.prefix:*/auth_tokens
	ALTER COLUMN uid SET NOT NULL,
	ADD CONSTRAINT /*:cms.obj:*/uc_auth_tokens_uid UNIQUE (uid),
	ADD CONSTRAINT /*:cms.obj:*/ck_auth_tokens_uid CHECK (char_length(uid) <= 64),
	ADD CONSTRAINT /*:cms.obj:*/ck_auth_tokens_name CHECK (char_length(name) <= 255);

CREATE INDEX /*:cms.obj:*/ix_auth_tokens_usr ON /*:cms.prefix:*/auth_tokens (usr);

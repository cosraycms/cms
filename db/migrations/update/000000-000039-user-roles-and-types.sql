-- Roles are defined in code, so the roles table and its foreign key go; a
-- user holds any number of them. `type` names the user model, and 'system'
-- replaces the system role for the seeded system user.

ALTER TABLE /*:cms.prefix:*/users
	ADD COLUMN roles text[] NOT NULL DEFAULT '{}',
	ADD COLUMN type text NOT NULL DEFAULT 'user'
		CONSTRAINT /*:cms.obj:*/ck_users_type CHECK (char_length(type) > 0 AND char_length(type) <= 64);

ALTER TABLE /*:cms.prefix:*/users_history
	ADD COLUMN roles text[] NOT NULL DEFAULT '{}',
	ADD COLUMN type text NOT NULL DEFAULT 'user';

ALTER TABLE /*:cms.prefix:*/users DISABLE TRIGGER USER;

UPDATE /*:cms.prefix:*/users SET type = 'system' WHERE rolename = 'system';
UPDATE /*:cms.prefix:*/users SET roles = ARRAY[rolename] WHERE rolename != 'system';
UPDATE /*:cms.prefix:*/users_history SET type = 'system' WHERE rolename = 'system';
UPDATE /*:cms.prefix:*/users_history SET roles = ARRAY[rolename] WHERE rolename != 'system';

ALTER TABLE /*:cms.prefix:*/users ENABLE TRIGGER USER;

CREATE OR REPLACE FUNCTION /*:cms.prefix:*/record_user_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO /*:cms.prefix:*/users_history (
		usr, type, username, email, password, roles, active,
		data, panel_locale, editor, changed, deleted
	) VALUES (
		OLD.usr, OLD.type, OLD.username, OLD.email, OLD.password, OLD.roles, OLD.active,
		OLD.data, OLD.panel_locale, OLD.editor, OLD.changed, OLD.deleted
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate user history row skipped. user: %, changed: %', OLD.usr, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

ALTER TABLE /*:cms.prefix:*/users
	DROP CONSTRAINT /*:cms.obj:*/fk_users_roles,
	DROP COLUMN rolename;

ALTER TABLE /*:cms.prefix:*/users_history
	DROP COLUMN rolename;

DROP TABLE /*:cms.prefix:*/roles;

-- The login lookup matches a login against both the email and the username,
-- so a username must never be able to equal another account's email.
ALTER TABLE /*:cms.prefix:*/users
	DROP CONSTRAINT /*:cms.obj:*/ck_users_username_or_email,
	ADD CONSTRAINT /*:cms.obj:*/ck_users_email_required
		CHECK (deleted IS NOT NULL OR email IS NOT NULL),
	DROP CONSTRAINT /*:cms.obj:*/ck_users_username,
	ADD CONSTRAINT /*:cms.obj:*/ck_users_username CHECK (
		username IS NULL OR (
			char_length(username) > 0
			AND char_length(username) <= 64
			AND username NOT LIKE '%@%'
		)
	);

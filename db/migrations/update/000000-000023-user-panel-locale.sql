-- Adds the panel UI language preference. NULL inherits the negotiated
-- default (config `panel.locale`, then the browser's Accept-Language).
-- Values are validated at the application layer against the locales that
-- ship panel catalogs; the check below is only a database-level safeguard.

ALTER TABLE /*:cms.prefix:*/users
	ADD COLUMN panel_locale text
	CONSTRAINT /*:cms.obj:*/ck_users_panel_locale
		CHECK (panel_locale IS NULL OR char_length(panel_locale) <= 32);

ALTER TABLE /*:cms.prefix:*/users_history
	ADD COLUMN panel_locale text;

CREATE OR REPLACE FUNCTION /*:cms.prefix:*/record_user_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO /*:cms.prefix:*/users_history (
		usr, username, email, password, rolename, active,
		data, panel_locale, editor, changed, deleted
	) VALUES (
		OLD.usr, OLD.username, OLD.email, OLD.password, OLD.rolename, OLD.active,
		OLD.data, OLD.panel_locale, OLD.editor, OLD.changed, OLD.deleted
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate user history row skipped. user: %, changed: %', OLD.usr, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

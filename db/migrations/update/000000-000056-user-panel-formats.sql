-- Adds the panel's regional format and time zone preferences. NULL
-- inherits the defaults: the browser's region or `panel.region`, and
-- `app.timezone`. Values are validated at the application layer; the
-- checks below are only database-level safeguards.

ALTER TABLE /*:cms.prefix:*/users
	ADD COLUMN panel_region text
	CONSTRAINT /*:cms.obj:*/ck_users_panel_region
		CHECK (panel_region IS NULL OR char_length(panel_region) <= 3),
	ADD COLUMN panel_timezone text
	CONSTRAINT /*:cms.obj:*/ck_users_panel_timezone
		CHECK (panel_timezone IS NULL OR char_length(panel_timezone) <= 64);

ALTER TABLE /*:cms.prefix:*/users_history
	ADD COLUMN panel_region text,
	ADD COLUMN panel_timezone text;

CREATE OR REPLACE FUNCTION /*:cms.prefix:*/record_user_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO /*:cms.prefix:*/users_history (
		usr, type, username, email, password, roles, active,
		data, panel_locale, panel_region, panel_timezone, editor, changed, deleted
	) VALUES (
		OLD.usr, OLD.type, OLD.username, OLD.email, OLD.password, OLD.roles, OLD.active,
		OLD.data, OLD.panel_locale, OLD.panel_region, OLD.panel_timezone, OLD.editor, OLD.changed, OLD.deleted
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate user history row skipped. user: %, changed: %', OLD.usr, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

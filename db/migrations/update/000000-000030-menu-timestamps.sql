-- When a menu and its items were written, and by whom. Not a history table:
-- the 2026-08-28 decision against history triggers on the menu tables stands,
-- and these are columns, not an audit trail.
--
-- `creator` and `editor` are NOT NULL, matching `nodes` and `assets`.
-- `Cosray\Menus` has no request context, so its write methods take an
-- optional actor and fall back to the system user — which is the honest
-- answer for a write coming from a migration or an import, and spares every
-- reader a null branch.
--
-- Existing rows are backfilled to the system user with the current time; the
-- real values were never recorded and cannot be recovered.
--
-- The `DEFAULT 1` stays after the backfill, unlike on `nodes` and `assets`:
-- menus are routinely seeded by raw INSERTs in site migrations, and the
-- system user is the right answer for those anyway.

ALTER TABLE /*:cms.prefix:*/menus
	ADD COLUMN created timestamp with time zone NOT NULL DEFAULT now(),
	ADD COLUMN changed timestamp with time zone NOT NULL DEFAULT now(),
	ADD COLUMN creator bigint NOT NULL DEFAULT 1,
	ADD COLUMN editor bigint NOT NULL DEFAULT 1,
	ADD CONSTRAINT /*:cms.obj:*/fk_menus_users_creator FOREIGN KEY (creator)
		REFERENCES /*:cms.prefix:*/users (usr),
	ADD CONSTRAINT /*:cms.obj:*/fk_menus_users_editor FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr);

ALTER TABLE /*:cms.prefix:*/menu_items
	ADD COLUMN created timestamp with time zone NOT NULL DEFAULT now(),
	ADD COLUMN changed timestamp with time zone NOT NULL DEFAULT now(),
	ADD COLUMN creator bigint NOT NULL DEFAULT 1,
	ADD COLUMN editor bigint NOT NULL DEFAULT 1,
	ADD CONSTRAINT /*:cms.obj:*/fk_menu_items_users_creator FOREIGN KEY (creator)
		REFERENCES /*:cms.prefix:*/users (usr),
	ADD CONSTRAINT /*:cms.obj:*/fk_menu_items_users_editor FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr);

CREATE TRIGGER /*:cms.obj:*/menus_trigger_01_change BEFORE UPDATE
	ON /*:cms.prefix:*/menus
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();

CREATE TRIGGER /*:cms.obj:*/menu_items_trigger_01_change BEFORE UPDATE
	ON /*:cms.prefix:*/menu_items
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();

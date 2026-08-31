-- How deep a menu's tree may be built. NULL means unlimited and is the
-- default, so existing menus are unaffected.
--
-- This is an authoring constraint, not a rendering one: a template is free to
-- output fewer levels than the menu allows, and the same menu may be rendered
-- fully elsewhere. What the column prevents is building a level that no
-- template was ever meant to show.
--
-- `Cosray\Menus` enforces it on every write, so imports and site migrations
-- going through the write API are covered too.

ALTER TABLE /*:cms.prefix:*/menus
	ADD COLUMN max_depth integer
	CONSTRAINT /*:cms.obj:*/ck_menus_max_depth
		CHECK (max_depth IS NULL OR max_depth BETWEEN 1 AND 10);

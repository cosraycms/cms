-- The self-referencing FK only guaranteed that the parent exists, not that it
-- belongs to the same menu. `Menus::assertMove()` enforces that in PHP; the
-- composite FK makes it hold for imports and hand-written site migrations too.
--
-- A cross-menu parent in an existing database would fail this migration. Such
-- a row is already broken and needs a decision, not an automatic fix:
--
--   SELECT i.item, i.menu, p.menu AS parent_menu
--   FROM cms.menu_items i JOIN cms.menu_items p ON p.item = i.parent
--   WHERE p.menu <> i.menu;
--
-- The index matches how the tree is read: one sibling group at a time,
-- ordered by position.

ALTER TABLE /*:cms.prefix:*/menu_items
	ADD CONSTRAINT /*:cms.obj:*/uc_menu_items_item_menu UNIQUE (item, menu);

ALTER TABLE /*:cms.prefix:*/menu_items
	DROP CONSTRAINT /*:cms.obj:*/fk_menu_items_menu_items;

ALTER TABLE /*:cms.prefix:*/menu_items
	ADD CONSTRAINT /*:cms.obj:*/fk_menu_items_menu_items
		FOREIGN KEY (parent, menu)
		REFERENCES /*:cms.prefix:*/menu_items (item, menu);

CREATE INDEX /*:cms.obj:*/ix_menu_items_tree
	ON /*:cms.prefix:*/menu_items USING btree (menu, parent, position);

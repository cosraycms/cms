-- Lets an editor take a menu entry out of the site without deleting it —
-- an unfinished page, a seasonal link. A real column rather than a key in
-- `data` because the finder filters on it.
--
-- Hiding a parent hides its subtree: the tree is nested by parent, so a
-- dropped node takes its children with it instead of orphaning them to the
-- root.

ALTER TABLE /*:cms.prefix:*/menu_items
	ADD COLUMN hidden boolean NOT NULL DEFAULT false;

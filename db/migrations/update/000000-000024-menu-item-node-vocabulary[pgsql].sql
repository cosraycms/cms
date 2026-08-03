-- Migration: Rename the legacy 'page' vocabulary in menu item payloads

-- `data->>'type'` drives Finder\Menu: only linked items are wrapped in an
-- anchor, everything else renders as plain markup. Nothing in the model is
-- a page, so the linked type is 'node' now.
--
-- The 'page' key held the id of the linked node. Nothing reads it today —
-- the panel that wrote it is gone and many ids no longer resolve — so it
-- is renamed for consistency, not repaired.
--
-- menu_items carries no change or history triggers, so unlike the node
-- content migrations this one needs no trigger juggling.
--
-- Key existence is tested with `-> ... IS NOT NULL` rather than the jsonb
-- `?` operator: migrations run through PDO::prepare(), which would claim
-- the question mark as a positional placeholder.

UPDATE /*:cms.prefix:*/menu_items
SET data = jsonb_set(data, '{type}', '"node"')
WHERE data->>'type' = 'page';

UPDATE /*:cms.prefix:*/menu_items
SET data = (data - 'page') || jsonb_build_object('node', data->'page')
WHERE data->'page' IS NOT NULL
	AND data->'node' IS NULL;

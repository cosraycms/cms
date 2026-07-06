-- Adds the materialized, query-only node title column: a locale map
-- ({locale: text}, or {zxx: text} when not language-specific), written at
-- save time from the type's title source. `Node::title()` stays the
-- authoritative resolver; this column exists for search, labelling and
-- locale-aware sorting.
--
-- Backfill and the per-locale sort indexes are NOT done here: resolving a
-- title needs the node type registry (title field / Contract\Title), which
-- lives on the booted app and is absent in the bare migration context. Run
-- `php run db:titles` to backfill and `php run db:recreate-sort-index` to
-- build the sort indexes once the app is deployed.

ALTER TABLE /*:cms.prefix:*/nodes
	ADD COLUMN IF NOT EXISTS title jsonb NOT NULL DEFAULT '{}';

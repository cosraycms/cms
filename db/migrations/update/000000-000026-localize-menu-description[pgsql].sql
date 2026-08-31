-- Menu descriptions become locale maps, matching every other label in the
-- schema (`topics.name`, `tags.name`, `nodes.title`).
--
-- Stored values were never language-specific, so they move under the neutral
-- key `zxx` — the same fallback `Field::NEUTRAL_LOCALE` and
-- `Title\Sort::expression()` already use, so a site that never translates its
-- descriptions keeps working unchanged.
--
-- The length check does not come back: the other locale maps carry none
-- either, a CHECK cannot hold the subquery this one would need, and
-- `Controller\Panel\Menus::validate()` enforces the limit per variant.

ALTER TABLE /*:cms.prefix:*/menus
	DROP CONSTRAINT IF EXISTS /*:cms.obj:*/ck_menus_description;

ALTER TABLE /*:cms.prefix:*/menus
	ALTER COLUMN description TYPE jsonb
	USING jsonb_build_object('zxx', description);

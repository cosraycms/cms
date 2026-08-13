-- The classification is derived from the mime type (`Asset::classify`), so the
-- stored column is redundant.
--
-- PostgreSQL would drop the check along with the column it depends on; naming
-- it here keeps the migration off that behaviour.

ALTER TABLE /*:cms.prefix:*/assets
	DROP CONSTRAINT IF EXISTS /*:cms.obj:*/ck_assets_kind;

ALTER TABLE /*:cms.prefix:*/assets
	DROP COLUMN IF EXISTS kind;

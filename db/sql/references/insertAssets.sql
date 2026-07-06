-- Joining against the catalog skips dangling uids instead of tripping
-- the FK: a save must never fail on a dangling content reference.
INSERT INTO /*:cms.prefix:*/asset_references (
	owner_type,
	owner_uid,
	asset_uid
)
SELECT
	:ownerType,
	:ownerUid,
	a.uid
FROM
	/*:cms.prefix:*/assets a
WHERE
	a.uid IN (SELECT jsonb_array_elements_text(:uids::jsonb))
ON CONFLICT DO NOTHING;

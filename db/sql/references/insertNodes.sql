-- Joining against nodes skips dangling uids instead of tripping the
-- FK: a save must never fail on a dangling content reference.
INSERT INTO /*:cms.prefix:*/node_references (
	owner_type,
	owner_uid,
	target_uid
)
SELECT
	:ownerType,
	:ownerUid,
	n.uid
FROM
	/*:cms.prefix:*/nodes n
WHERE
	n.uid IN (SELECT jsonb_array_elements_text(:uids::jsonb))
ON CONFLICT DO NOTHING;

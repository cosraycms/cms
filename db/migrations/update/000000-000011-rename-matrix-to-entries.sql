-- Migration: Rename field type 'matrix' to 'entries' in JSONB content

-- This is a mechanical schema update. Disable history/change triggers so the
-- migration neither bumps changed timestamps nor creates ghost history rows.
ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
ALTER TABLE /*:cms.prefix:*/drafts DISABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;

-- Update current node content.
UPDATE /*:cms.prefix:*/nodes
SET content = regexp_replace(
	content::text,
	'("type"\s*:\s*)"matrix"',
	'\1"entries"',
	'g'
)::jsonb
WHERE content::text ~ '("type"\s*:\s*)"matrix"';

-- Update draft content.
UPDATE /*:cms.prefix:*/drafts
SET content = regexp_replace(
	content::text,
	'("type"\s*:\s*)"matrix"',
	'\1"entries"',
	'g'
)::jsonb
WHERE content::text ~ '("type"\s*:\s*)"matrix"';

-- Update existing history rows in place.
UPDATE /*:cms.prefix:*/nodes_history
SET content = regexp_replace(
	content::text,
	'("type"\s*:\s*)"matrix"',
	'\1"entries"',
	'g'
)::jsonb
WHERE content::text ~ '("type"\s*:\s*)"matrix"';

UPDATE /*:cms.prefix:*/drafts_history
SET content = regexp_replace(
	content::text,
	'("type"\s*:\s*)"matrix"',
	'\1"entries"',
	'g'
)::jsonb
WHERE content::text ~ '("type"\s*:\s*)"matrix"';

ALTER TABLE /*:cms.prefix:*/drafts ENABLE TRIGGER /*:cms.obj:*/drafts_trigger_01_history;
ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;

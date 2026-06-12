-- Migration: Rename field type 'grid' to 'blocks' in JSONB content

-- This is a mechanical schema update. Disable history/change triggers so the
-- migration neither bumps changed timestamps nor creates ghost history rows.
ALTER TABLE cms.nodes DISABLE TRIGGER nodes_trigger_02_change;
ALTER TABLE cms.nodes DISABLE TRIGGER nodes_trigger_03_history;
ALTER TABLE cms.drafts DISABLE TRIGGER drafts_trigger_01_history;

-- Update current node content.
UPDATE cms.nodes
SET content = regexp_replace(
	content::text,
	'("type"\s*:\s*)"grid"',
	'\1"blocks"',
	'g'
)::jsonb
WHERE content::text ~ '("type"\s*:\s*)"grid"';

-- Update draft content.
UPDATE cms.drafts
SET content = regexp_replace(
	content::text,
	'("type"\s*:\s*)"grid"',
	'\1"blocks"',
	'g'
)::jsonb
WHERE content::text ~ '("type"\s*:\s*)"grid"';

-- Update existing history rows in place.
UPDATE cms.nodes_history
SET content = regexp_replace(
	content::text,
	'("type"\s*:\s*)"grid"',
	'\1"blocks"',
	'g'
)::jsonb
WHERE content::text ~ '("type"\s*:\s*)"grid"';

UPDATE cms.drafts_history
SET content = regexp_replace(
	content::text,
	'("type"\s*:\s*)"grid"',
	'\1"blocks"',
	'g'
)::jsonb
WHERE content::text ~ '("type"\s*:\s*)"grid"';

ALTER TABLE cms.drafts ENABLE TRIGGER drafts_trigger_01_history;
ALTER TABLE cms.nodes ENABLE TRIGGER nodes_trigger_03_history;
ALTER TABLE cms.nodes ENABLE TRIGGER nodes_trigger_02_change;

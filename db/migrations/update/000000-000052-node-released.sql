-- When a node's content last went live; NULL until its first publication.
-- Backfilled from the online state: an online node went live with its last
-- change, an offline one with the last version history shows online.
ALTER TABLE /*:cms.prefix:*/nodes ADD COLUMN released timestamp with time zone;

ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;

UPDATE /*:cms.prefix:*/nodes n
SET
	released = CASE
		WHEN n.published THEN n.changed
		ELSE (
			SELECT max(h.changed)
			FROM /*:cms.prefix:*/nodes_history h
			WHERE h.node = n.node AND h.published
		)
	END;

ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;

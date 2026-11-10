-- When the content, title, parent or deletion changed last; publishing,
-- listing and locking leave it alone. Unsaved edits are based on it.
ALTER TABLE /*:cms.prefix:*/nodes ADD COLUMN edited timestamp with time zone;
ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
ALTER TABLE /*:cms.prefix:*/nodes DISABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
UPDATE /*:cms.prefix:*/nodes SET edited = changed;
ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_03_history;
ALTER TABLE /*:cms.prefix:*/nodes ENABLE TRIGGER /*:cms.obj:*/nodes_trigger_02_change;
ALTER TABLE /*:cms.prefix:*/nodes ALTER COLUMN edited SET DEFAULT now();
ALTER TABLE /*:cms.prefix:*/nodes ALTER COLUMN edited SET NOT NULL;

CREATE FUNCTION /*:cms.prefix:*/update_node_edited()
	RETURNS TRIGGER AS $$
BEGIN
	IF (NEW.parent, NEW.type, NEW.content, NEW.title, NEW.deleted)
		IS NOT DISTINCT FROM (OLD.parent, OLD.type, OLD.content, OLD.title, OLD.deleted)
		AND (NEW.published, NEW.hidden, NEW.locked) IS DISTINCT FROM (OLD.published, OLD.hidden, OLD.locked)
	THEN
		NEW.edited = OLD.edited;
	ELSE
		NEW.edited = NEW.changed;
	END IF;

	RETURN NEW;
END;
$$ LANGUAGE plpgsql;
-- Runs after the change trigger, whose time it takes.
CREATE TRIGGER /*:cms.obj:*/nodes_trigger_04_edited BEFORE UPDATE ON /*:cms.prefix:*/nodes
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_node_edited();

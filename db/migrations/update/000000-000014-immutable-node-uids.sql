CREATE FUNCTION /*:cms.prefix:*/prevent_node_uid_update()
	RETURNS TRIGGER AS $$
BEGIN
	IF NEW.uid IS DISTINCT FROM OLD.uid THEN
		RAISE EXCEPTION 'Node uid is immutable';
	END IF;

	RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER /*:cms.obj:*/nodes_trigger_01_uid BEFORE UPDATE OF uid ON /*:cms.prefix:*/nodes
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/prevent_node_uid_update();

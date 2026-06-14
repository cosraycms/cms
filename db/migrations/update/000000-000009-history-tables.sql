DROP TRIGGER IF EXISTS users_trigger_02_audit ON cms.users;
DROP TRIGGER IF EXISTS users_trigger_03_audit ON cms.users;
DROP TRIGGER IF EXISTS nodes_trigger_03_audit ON cms.nodes;
DROP TRIGGER IF EXISTS drafts_trigger_01_audit ON cms.drafts;

ALTER TABLE audit.users RENAME CONSTRAINT pk_users TO pk_users_history;
ALTER TABLE audit.users RENAME CONSTRAINT fk_audit_users TO fk_users_history_users;
ALTER TABLE audit.users RENAME TO users_history;
ALTER TABLE audit.users_history SET SCHEMA cms;

ALTER TABLE audit.nodes RENAME CONSTRAINT pk_nodes TO pk_nodes_history;
ALTER TABLE audit.nodes RENAME CONSTRAINT fk_audit_nodes TO fk_nodes_history_nodes;
ALTER TABLE audit.nodes RENAME TO nodes_history;
ALTER TABLE audit.nodes_history SET SCHEMA cms;

ALTER TABLE audit.drafts RENAME CONSTRAINT pk_drafts TO pk_drafts_history;
ALTER TABLE audit.drafts RENAME CONSTRAINT fk_audit_drafts TO fk_drafts_history_drafts;
ALTER TABLE audit.drafts RENAME TO drafts_history;
ALTER TABLE audit.drafts_history SET SCHEMA cms;

CREATE FUNCTION cms.record_user_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO cms.users_history (
		usr, username, email, password, rolename, active,
		data, editor, changed, deleted
	) VALUES (
		OLD.usr, OLD.username, OLD.email, OLD.password, OLD.rolename, OLD.active,
		OLD.data, OLD.editor, OLD.changed, OLD.deleted
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate user history row skipped. user: %, changed: %', OLD.usr, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE FUNCTION cms.record_node_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO cms.nodes_history (
		node, parent, version, changed, published, hidden, locked,
		type, editor, deleted, content
	) VALUES (
		OLD.node, OLD.parent, OLD.version, OLD.changed, OLD.published, OLD.hidden, OLD.locked,
		OLD.type, OLD.editor, OLD.deleted, OLD.content
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate node history row skipped. node: %, changed: %', OLD.node, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE FUNCTION cms.record_draft_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO cms.drafts_history (
		node, changed, editor, content
	) VALUES (
		OLD.node, OLD.changed, OLD.editor, OLD.content
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate draft history row skipped. draft: %, changed: %', OLD.node, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER users_trigger_02_history AFTER UPDATE
	ON cms.users FOR EACH ROW EXECUTE FUNCTION
	cms.record_user_history();

CREATE TRIGGER nodes_trigger_03_history AFTER UPDATE
	ON cms.nodes FOR EACH ROW EXECUTE FUNCTION
	cms.record_node_history();

CREATE TRIGGER drafts_trigger_01_history AFTER UPDATE
	ON cms.drafts FOR EACH ROW EXECUTE FUNCTION
	cms.record_draft_history();

DROP FUNCTION cms.process_users_audit();
DROP FUNCTION cms.process_nodes_audit();
DROP FUNCTION cms.process_drafts_audit();

DROP SCHEMA audit;

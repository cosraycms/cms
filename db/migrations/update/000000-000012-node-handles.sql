CREATE TABLE /*:cms.prefix:*/node_handles (
	handle text NOT NULL,
	node bigint NOT NULL,
	creator bigint NOT NULL,
	editor bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	changed timestamp with time zone NOT NULL DEFAULT now(),
	CONSTRAINT /*:cms.obj:*/pk_node_handles PRIMARY KEY (handle),
	CONSTRAINT /*:cms.obj:*/uc_node_handles_node UNIQUE (node),
	CONSTRAINT /*:cms.obj:*/fk_node_handles_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node) ON DELETE CASCADE,
	CONSTRAINT /*:cms.obj:*/fk_node_handles_users_creator FOREIGN KEY (creator)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/fk_node_handles_users_editor FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/ck_node_handles_handle CHECK (
		-- Handles can become filesystem directory names, so keep them path-safe and block "..".
		handle ~ '^(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?$'
	)
);

CREATE TRIGGER /*:cms.obj:*/node_handles_trigger_01_change BEFORE UPDATE ON /*:cms.prefix:*/node_handles
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();

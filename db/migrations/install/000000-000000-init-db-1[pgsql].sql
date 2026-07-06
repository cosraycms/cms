CREATE FUNCTION /*:cms.prefix:*/update_changed_column()
	RETURNS TRIGGER AS $$
BEGIN
   NEW.changed = now();
   RETURN NEW;
END;
$$ LANGUAGE plpgsql;


CREATE TABLE /*:cms.prefix:*/roles (
	rolename text NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_roles PRIMARY KEY (rolename)
);


CREATE TABLE /*:cms.prefix:*/users (
	usr bigint GENERATED ALWAYS AS IDENTITY,
	uid text NOT NULL,
	username text,
	email text,
	password text NOT NULL,
	rolename text NOT NULL,
	active boolean NOT NULL,
	data jsonb NOT NULL,
	creator bigint NOT NULL,
	editor bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	changed timestamp with time zone NOT NULL DEFAULT now(),
	deleted timestamp with time zone,
	CONSTRAINT /*:cms.obj:*/pk_users PRIMARY KEY (usr),
	CONSTRAINT /*:cms.obj:*/uc_users_uid UNIQUE (uid),
	CONSTRAINT /*:cms.obj:*/fk_users_roles FOREIGN KEY (rolename)
		REFERENCES /*:cms.prefix:*/roles (rolename) ON UPDATE CASCADE,
	CONSTRAINT /*:cms.obj:*/fk_users_users_creator FOREIGN KEY (creator)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/fk_users_users_editor FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/ck_users_uid CHECK (char_length(uid) <= 64),
	CONSTRAINT /*:cms.obj:*/ck_users_username_or_email CHECK (deleted IS NOT NULL OR username IS NOT NULL OR email IS NOT NULL),
	CONSTRAINT /*:cms.obj:*/ck_users_username CHECK
		(username IS NULL OR (char_length(username) > 0 AND char_length(username) <= 64)),
	CONSTRAINT /*:cms.obj:*/ck_users_email CHECK (
		-- This is not full RFC email validation.
		-- It only rejects obviously malformed addresses as a last database-level safeguard.
		email IS NULL OR (
			char_length(email) <= 254
			AND email !~ '[[:space:]]'
			AND email NOT LIKE '%..%'
			AND email NOT LIKE '%.@%'
			AND email NOT LIKE '%@.%'
			AND email ~ '^[^@]+@[^@]+[.][^@]+$'
		)
	)
);
CREATE UNIQUE INDEX /*:cms.obj:*/ux_users_username ON /*:cms.prefix:*/users
	USING btree (lower(username)) WHERE (deleted IS NULL AND username IS NOT NULL);
CREATE UNIQUE INDEX /*:cms.obj:*/ux_users_email ON /*:cms.prefix:*/users
	USING btree (lower(email)) WHERE (deleted IS NULL AND email IS NOT NULL);
CREATE FUNCTION /*:cms.prefix:*/record_user_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO /*:cms.prefix:*/users_history (
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
CREATE TRIGGER /*:cms.obj:*/users_trigger_01_change BEFORE UPDATE ON /*:cms.prefix:*/users
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();
CREATE TRIGGER /*:cms.obj:*/users_trigger_02_history AFTER UPDATE
	ON /*:cms.prefix:*/users FOR EACH ROW EXECUTE FUNCTION
	/*:cms.prefix:*/record_user_history();


CREATE TABLE /*:cms.prefix:*/auth_tokens (
	token text NOT NULL,
	usr bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	changed timestamp with time zone NOT NULL DEFAULT now(),
	creator bigint NOT NULL,
	editor bigint NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_auth_tokens PRIMARY KEY (token),
	CONSTRAINT /*:cms.obj:*/fk_auth_tokens_users FOREIGN KEY (usr)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/fk_auth_tokens_users_creator FOREIGN KEY (creator)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/fk_auth_tokens_users_editor FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/uc_auth_tokens_usr UNIQUE (usr),
	CONSTRAINT /*:cms.obj:*/ck_auth_tokens_token CHECK (char_length(token) <= 512)
);
CREATE TRIGGER /*:cms.obj:*/auth_tokens_trigger_01_change BEFORE UPDATE ON /*:cms.prefix:*/auth_tokens
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();


CREATE TABLE /*:cms.prefix:*/one_time_tokens (
	token text NOT NULL,
	usr bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	CONSTRAINT /*:cms.obj:*/pk_one_time_tokens PRIMARY KEY (token),
	CONSTRAINT /*:cms.obj:*/fk_one_time_tokens_users FOREIGN KEY (usr)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/ck_one_time_tokens_token CHECK (char_length(token) <= 512)
);


CREATE TABLE /*:cms.prefix:*/login_sessions (
	hash text NOT NULL,
	usr bigint NOT NULL,
	expires timestamp with time zone NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_login_sessions PRIMARY KEY (hash),
	CONSTRAINT /*:cms.obj:*/uc_login_sessions_usr UNIQUE (usr),
	CONSTRAINT /*:cms.obj:*/fk_login_sessions_users FOREIGN KEY (usr) REFERENCES /*:cms.prefix:*/users(usr),
	CONSTRAINT /*:cms.obj:*/ck_login_sessions_hash CHECK (char_length(hash) <= 254)
);


CREATE TABLE /*:cms.prefix:*/types (
	type bigint GENERATED ALWAYS AS IDENTITY,
	handle text NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_types PRIMARY KEY (type),
	CONSTRAINT /*:cms.obj:*/uc_types_handle UNIQUE (handle),
	CONSTRAINT /*:cms.obj:*/ck_types_handle CHECK (char_length(handle) <= 256)
);


CREATE TABLE /*:cms.prefix:*/nodes (
	node bigint GENERATED ALWAYS AS IDENTITY,
	uid text NOT NULL,
	parent bigint,
	version integer NOT NULL DEFAULT 1,
	published boolean DEFAULT false NOT NULL,
	hidden boolean DEFAULT false NOT NULL,
	locked boolean DEFAULT false NOT NULL,
	type bigint NOT NULL,
	creator bigint NOT NULL,
	editor bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	changed timestamp with time zone NOT NULL DEFAULT now(),
	deleted timestamp with time zone,
	content jsonb NOT NULL,
	-- Materialized, query-only copy of the node's title as a locale map
	-- ({locale: text}, or {zxx: text} when not language-specific). Written
	-- at save time from the type's title source; `title()` stays authoritative.
	title jsonb NOT NULL DEFAULT '{}',
	CONSTRAINT /*:cms.obj:*/pk_nodes PRIMARY KEY (node),
	CONSTRAINT /*:cms.obj:*/uc_nodes_uid UNIQUE (uid),
	CONSTRAINT /*:cms.obj:*/fk_nodes_users_creator FOREIGN KEY (creator)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/fk_nodes_nodes FOREIGN KEY (parent)
		REFERENCES /*:cms.prefix:*/nodes (node),
	CONSTRAINT /*:cms.obj:*/fk_nodes_users_editor FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/fk_nodes_types FOREIGN KEY (type)
		REFERENCES /*:cms.prefix:*/types (type) ON UPDATE CASCADE ON DELETE NO ACTION,
	CONSTRAINT /*:cms.obj:*/ck_nodes_uid CHECK (
		-- UIDs can become filesystem directory names, so keep them path-safe and block "..".
		uid ~ '^(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?$'
	),
	CONSTRAINT /*:cms.obj:*/ck_nodes_version CHECK (version > 0)
);
CREATE INDEX /*:cms.obj:*/ix_nodes_type ON /*:cms.prefix:*/nodes USING btree (type);
CREATE INDEX /*:cms.obj:*/ix_nodes_content ON /*:cms.prefix:*/nodes USING GIN (content);


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


CREATE FUNCTION /*:cms.prefix:*/record_node_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO /*:cms.prefix:*/nodes_history (
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
CREATE TRIGGER /*:cms.obj:*/nodes_trigger_02_change BEFORE UPDATE ON /*:cms.prefix:*/nodes
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();
CREATE TRIGGER /*:cms.obj:*/nodes_trigger_03_history AFTER UPDATE
	ON /*:cms.prefix:*/nodes FOR EACH ROW EXECUTE FUNCTION
	/*:cms.prefix:*/record_node_history();


CREATE TABLE /*:cms.prefix:*/full_text (
	node bigint NOT NULL,
	locale text NOT NULL,
	document tsvector NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_full_text PRIMARY KEY (node, locale),
	CONSTRAINT /*:cms.obj:*/fk_full_text_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node),
	CONSTRAINT /*:cms.obj:*/ck_full_text_locale CHECK (char_length(locale) <= 32)
);
CREATE INDEX /*:cms.obj:*/ix_nodes_tsv ON /*:cms.prefix:*/full_text USING GIN(document);


CREATE TABLE /*:cms.prefix:*/url_paths (
	node bigint NOT NULL,
	path text NOT NULL,
	locale text NOT NULL,
	creator bigint NOT NULL,
	editor bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	inactive timestamp with time zone,
	CONSTRAINT /*:cms.obj:*/pk_url_paths PRIMARY KEY (node, locale, path),
	CONSTRAINT /*:cms.obj:*/fk_url_paths_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node),
	CONSTRAINT /*:cms.obj:*/fk_url_paths_users_creator FOREIGN KEY (creator)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/fk_url_paths_users_editor FOREIGN KEY (editor)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/ck_url_paths_path CHECK (char_length(path) <= 512),
	CONSTRAINT /*:cms.obj:*/ck_url_paths_locale CHECK (char_length(locale) <= 32)
);
CREATE UNIQUE INDEX /*:cms.obj:*/ux_url_paths_path ON /*:cms.prefix:*/url_paths
	USING btree (path);
CREATE UNIQUE INDEX /*:cms.obj:*/ux_url_paths_locale ON /*:cms.prefix:*/url_paths
	USING btree (node, locale) WHERE (inactive IS NULL);


CREATE TABLE /*:cms.prefix:*/drafts (
	node bigint NOT NULL,
	changed timestamp with time zone NOT NULL,
	editor bigint NOT NULL,
	content jsonb NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_drafts PRIMARY KEY (node),
	CONSTRAINT /*:cms.obj:*/fk_drafts_nodes FOREIGN KEY (node) REFERENCES /*:cms.prefix:*/nodes (node)
);
CREATE FUNCTION /*:cms.prefix:*/record_draft_history()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO /*:cms.prefix:*/drafts_history (
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
CREATE TRIGGER /*:cms.obj:*/drafts_trigger_01_history AFTER UPDATE
	ON /*:cms.prefix:*/drafts FOR EACH ROW EXECUTE FUNCTION
	/*:cms.prefix:*/record_draft_history();


CREATE TABLE /*:cms.prefix:*/menus (
	menu text NOT NULL,
	description text NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_menus PRIMARY KEY (menu),
	CONSTRAINT /*:cms.obj:*/ck_menus_menu CHECK (char_length(menu) <= 32),
	CONSTRAINT /*:cms.obj:*/ck_menus_description CHECK (char_length(description) <= 128)
);


CREATE TABLE /*:cms.prefix:*/menu_items (
	item text NOT NULL,
	parent text,
	menu text NOT NULL,
	position integer NOT NULL,
	data jsonb NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_menu_items PRIMARY KEY (item),
	CONSTRAINT /*:cms.obj:*/fk_menu_items_menus FOREIGN KEY (menu)
		REFERENCES /*:cms.prefix:*/menus (menu) ON UPDATE CASCADE,
	CONSTRAINT /*:cms.obj:*/fk_menu_items_menu_items FOREIGN KEY (parent)
		REFERENCES /*:cms.prefix:*/menu_items (item),
	CONSTRAINT /*:cms.obj:*/ck_menu_items_item CHECK (char_length(item) <= 64),
	CONSTRAINT /*:cms.obj:*/ck_menu_items_parent CHECK (char_length(parent) <= 64)
);


CREATE TABLE /*:cms.prefix:*/topics (
	topic bigint GENERATED ALWAYS AS IDENTITY,
	uid text NOT NULL,
	name jsonb NOT NULL,
	color text NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_topics PRIMARY KEY (topic),
	CONSTRAINT /*:cms.obj:*/uc_topics_uid UNIQUE (uid),
	CONSTRAINT /*:cms.obj:*/ck_topics_uid CHECK (char_length(uid) <= 64),
	CONSTRAINT /*:cms.obj:*/ck_topics_color CHECK (char_length(color) <= 128)
);


CREATE TABLE /*:cms.prefix:*/tags (
	tag bigint GENERATED ALWAYS AS IDENTITY,
	uid text NOT NULL,
	name jsonb NOT NULL,
	topic bigint NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_tags PRIMARY KEY (tag),
	CONSTRAINT /*:cms.obj:*/uc_tags_uid UNIQUE (uid),
	CONSTRAINT /*:cms.obj:*/fk_tags_topics FOREIGN KEY (topic)
		REFERENCES /*:cms.prefix:*/topics (topic),
	CONSTRAINT /*:cms.obj:*/ck_tags_uid CHECK (char_length(uid) <= 64)
);


CREATE TABLE /*:cms.prefix:*/node_tags (
	node bigint NOT NULL,
	tag bigint NOT NULL,
	position integer NOT NULL DEFAULT 0,
	CONSTRAINT /*:cms.obj:*/pk_node_tags PRIMARY KEY (node, tag),
	CONSTRAINT /*:cms.obj:*/fk_node_tags_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node),
	CONSTRAINT /*:cms.obj:*/fk_node_tags_tags FOREIGN KEY (tag)
		REFERENCES /*:cms.prefix:*/tags (tag)
);


CREATE TABLE /*:cms.prefix:*/nodes_history (
	node bigint NOT NULL,
	parent bigint,
	version integer NOT NULL,
	changed timestamp with time zone NOT NULL,
	published boolean NOT NULL,
	hidden boolean NOT NULL,
	locked boolean NOT NULL,
	type bigint NOT NULL,
	editor bigint NOT NULL,
	deleted timestamp with time zone,
	content jsonb NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_nodes_history PRIMARY KEY (node, changed),
	CONSTRAINT /*:cms.obj:*/fk_nodes_history_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node)
);


CREATE TABLE /*:cms.prefix:*/drafts_history (
	node bigint NOT NULL,
	changed timestamp with time zone NOT NULL,
	editor bigint NOT NULL,
	content jsonb NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_drafts_history PRIMARY KEY (node, changed),
	CONSTRAINT /*:cms.obj:*/fk_drafts_history_drafts FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/drafts (node)
);


CREATE TABLE /*:cms.prefix:*/users_history (
	usr bigint NOT NULL,
	username text,
	email text,
	password text NOT NULL,
	rolename text NOT NULL,
	active boolean NOT NULL,
	data jsonb NOT NULL,
	editor bigint NOT NULL,
	changed timestamp with time zone NOT NULL DEFAULT now(),
	deleted timestamp with time zone,
	CONSTRAINT /*:cms.obj:*/pk_users_history PRIMARY KEY (usr, changed),
	CONSTRAINT /*:cms.obj:*/fk_users_history_users FOREIGN KEY (usr)
		REFERENCES /*:cms.prefix:*/users (usr)
);


CREATE TABLE /*:cms.prefix:*/assets (
	asset bigint GENERATED ALWAYS AS IDENTITY,
	uid text NOT NULL,
	disk text NOT NULL DEFAULT 'local',
	key text NOT NULL,
	filename text NOT NULL,
	mime text,
	bytes bigint,
	width integer,
	height integer,
	kind text NOT NULL,
	hash text,
	meta jsonb NOT NULL DEFAULT '{}'::jsonb,
	creator bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	changed timestamp with time zone NOT NULL DEFAULT now(),
	CONSTRAINT /*:cms.obj:*/pk_assets PRIMARY KEY (asset),
	CONSTRAINT /*:cms.obj:*/uc_assets_uid UNIQUE (uid),
	CONSTRAINT /*:cms.obj:*/uc_assets_disk_key UNIQUE (disk, key),
	CONSTRAINT /*:cms.obj:*/fk_assets_users_creator FOREIGN KEY (creator)
		REFERENCES /*:cms.prefix:*/users (usr),
	CONSTRAINT /*:cms.obj:*/ck_assets_uid CHECK (
		-- Asset uids become URL segments and storage keys, so keep them path-safe and block "..".
		uid ~ '^(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?$'
	),
	CONSTRAINT /*:cms.obj:*/ck_assets_kind CHECK (kind IN ('image', 'file', 'video'))
);
CREATE INDEX /*:cms.obj:*/ix_assets_hash ON /*:cms.prefix:*/assets USING btree (hash);
CREATE INDEX /*:cms.obj:*/ix_assets_key ON /*:cms.prefix:*/assets USING btree (key);
CREATE TRIGGER /*:cms.obj:*/assets_trigger_01_change BEFORE UPDATE ON /*:cms.prefix:*/assets
	FOR EACH ROW EXECUTE FUNCTION /*:cms.prefix:*/update_changed_column();


CREATE TABLE /*:cms.prefix:*/asset_references (
	owner_type text NOT NULL,
	owner_uid text NOT NULL,
	asset_uid text NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_asset_references PRIMARY KEY (owner_type, owner_uid, asset_uid),
	CONSTRAINT /*:cms.obj:*/fk_asset_references_assets FOREIGN KEY (asset_uid)
		REFERENCES /*:cms.prefix:*/assets (uid) ON DELETE RESTRICT,
	CONSTRAINT /*:cms.obj:*/ck_asset_references_owner_type CHECK (char_length(owner_type) <= 32),
	CONSTRAINT /*:cms.obj:*/ck_asset_references_owner_uid CHECK (char_length(owner_uid) <= 64)
);
CREATE INDEX /*:cms.obj:*/ix_asset_references_asset
	ON /*:cms.prefix:*/asset_references USING btree (asset_uid);


CREATE TABLE /*:cms.prefix:*/node_references (
	owner_type text NOT NULL,
	owner_uid text NOT NULL,
	target_uid text NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_node_references PRIMARY KEY (owner_type, owner_uid, target_uid),
	CONSTRAINT /*:cms.obj:*/fk_node_references_nodes FOREIGN KEY (target_uid)
		REFERENCES /*:cms.prefix:*/nodes (uid),
	CONSTRAINT /*:cms.obj:*/ck_node_references_owner_type CHECK (char_length(owner_type) <= 32),
	CONSTRAINT /*:cms.obj:*/ck_node_references_owner_uid CHECK (char_length(owner_uid) <= 64)
);
CREATE INDEX /*:cms.obj:*/ix_node_references_target
	ON /*:cms.prefix:*/node_references USING btree (target_uid);

CREATE EXTENSION btree_gist;
CREATE EXTENSION btree_gin;
CREATE EXTENSION unaccent;

CREATE SCHEMA cms;
CREATE SCHEMA audit;

CREATE FUNCTION cms.update_changed_column()
	RETURNS TRIGGER AS $$
BEGIN
   NEW.changed = now();
   RETURN NEW;
END;
$$ LANGUAGE plpgsql;


CREATE TABLE cms.roles (
	rolename text NOT NULL,
	CONSTRAINT pk_roles PRIMARY KEY (rolename)
);


CREATE TABLE cms.users (
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
	CONSTRAINT pk_users PRIMARY KEY (usr),
	CONSTRAINT uc_users_uid UNIQUE (uid),
	CONSTRAINT fk_users_roles FOREIGN KEY (rolename)
		REFERENCES cms.roles (rolename) ON UPDATE CASCADE,
	CONSTRAINT fk_users_users_creator FOREIGN KEY (creator)
		REFERENCES cms.users (usr),
	CONSTRAINT fk_users_users_editor FOREIGN KEY (editor)
		REFERENCES cms.users (usr),
	CONSTRAINT ck_users_uid CHECK (char_length(uid) <= 64),
	CONSTRAINT ck_users_username CHECK
		(username IS NULL OR (char_length(username) > 0 AND char_length(username) <= 64)),
	CONSTRAINT ck_users_email CHECK
		(email IS NULL OR (email SIMILAR TO '%@%' AND char_length(email) >= 5 AND char_length(email) <= 256)),
	CONSTRAINT ck_users_username_or_email CHECK (username IS NOT NULL OR email IS NOT NULL)
);
CREATE UNIQUE INDEX ux_users_username ON cms.users
	USING btree (lower(username)) WHERE (deleted IS NULL AND username IS NOT NULL);
CREATE UNIQUE INDEX ux_users_email ON cms.users
	USING btree (lower(email)) WHERE (deleted IS NULL AND email IS NOT NULL);
CREATE FUNCTION cms.process_users_audit()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO audit.users (
		usr, username, email, password, rolename, active,
		data, editor, changed, deleted
	) VALUES (
		OLD.usr, OLD.username, OLD.email, OLD.password, OLD.rolename, OLD.active,
		OLD.data, OLD.editor, OLD.changed, OLD.deleted
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate users audit row skipped. user: %, changed: %', OLD.usr, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER users_trigger_01_change BEFORE UPDATE ON cms.users
	FOR EACH ROW EXECUTE FUNCTION cms.update_changed_column();
CREATE TRIGGER users_trigger_02_audit AFTER UPDATE
	ON cms.users FOR EACH ROW EXECUTE PROCEDURE
	cms.process_users_audit();


CREATE TABLE cms.auth_tokens (
	token text NOT NULL,
	usr bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	changed timestamp with time zone NOT NULL DEFAULT now(),
	creator bigint NOT NULL,
	editor bigint NOT NULL,
	CONSTRAINT pk_auth_tokens PRIMARY KEY (token),
	CONSTRAINT fk_auth_tokens_users FOREIGN KEY (usr)
		REFERENCES cms.users (usr),
	CONSTRAINT fk_auth_tokens_users_creator FOREIGN KEY (creator)
		REFERENCES cms.users (usr),
	CONSTRAINT fk_auth_tokens_users_editor FOREIGN KEY (editor)
		REFERENCES cms.users (usr),
	CONSTRAINT uc_auth_tokens_usr UNIQUE (usr),
	CONSTRAINT ck_auth_tokens_token CHECK (char_length(token) <= 512)
);
CREATE TRIGGER auth_tokens_trigger_01_change BEFORE UPDATE ON cms.auth_tokens
	FOR EACH ROW EXECUTE FUNCTION cms.update_changed_column();


CREATE TABLE cms.one_time_tokens (
	token text NOT NULL,
	usr bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	CONSTRAINT pk_one_time_tokens PRIMARY KEY (token),
	CONSTRAINT fk_one_time_tokens_users FOREIGN KEY (usr)
		REFERENCES cms.users (usr),
	CONSTRAINT ck_one_time_tokens_token CHECK (char_length(token) <= 512)
);


CREATE TABLE cms.login_sessions (
	hash text NOT NULL,
	usr bigint NOT NULL,
	expires timestamp with time zone NOT NULL,
	CONSTRAINT pk_login_sessions PRIMARY KEY (hash),
	CONSTRAINT uc_login_sessions_usr UNIQUE (usr),
	CONSTRAINT fk_login_sessions_users FOREIGN KEY (usr) REFERENCES cms.users(usr),
	CONSTRAINT ck_login_sessions_hash CHECK (char_length(hash) <= 254)
);


CREATE TABLE cms.types (
	type bigint GENERATED ALWAYS AS IDENTITY,
	handle text NOT NULL,
	CONSTRAINT pk_types PRIMARY KEY (type),
	CONSTRAINT uc_types_handle UNIQUE (handle),
	CONSTRAINT ck_types_handle CHECK (char_length(handle) <= 256)
);


CREATE TABLE cms.nodes (
	node bigint GENERATED ALWAYS AS IDENTITY,
	uid text NOT NULL,
	parent bigint,
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
	CONSTRAINT pk_nodes PRIMARY KEY (node),
	CONSTRAINT uc_nodes_uid UNIQUE (uid),
	CONSTRAINT fk_nodes_users_creator FOREIGN KEY (creator)
		REFERENCES cms.users (usr),
	CONSTRAINT fk_nodes_nodes FOREIGN KEY (parent)
		REFERENCES cms.nodes (node),
	CONSTRAINT fk_nodes_users_editor FOREIGN KEY (editor)
		REFERENCES cms.users (usr),
	CONSTRAINT fk_nodes_types FOREIGN KEY (type)
		REFERENCES cms.types (type) ON UPDATE CASCADE ON DELETE NO ACTION,
	CONSTRAINT ck_nodes_uid CHECK (char_length(uid) <= 64)
);
CREATE INDEX ix_nodes_content ON cms.nodes USING GIN (type, content);
CREATE FUNCTION cms.process_nodes_audit()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO audit.nodes (
		node, parent, changed, published, hidden, locked,
		type, editor, deleted, content
	) VALUES (
		OLD.node, OLD.parent, OLD.changed, OLD.published, OLD.hidden, OLD.locked,
		OLD.type, OLD.editor, OLD.deleted, OLD.content
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate nodes audit row skipped. node: %, changed: %', OLD.node, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;
CREATE FUNCTION cms.check_if_deletable()
	RETURNS TRIGGER AS $$
BEGIN
	IF (
		NEW.deleted IS NOT NULL
		AND (
			SELECT count(*)
			FROM cms.menu_items mi
			WHERE
				mi.data->>'type' = 'node'
				AND mi.data->>'node' = OLD.node::text
		) > 0
	)
	THEN
		RAISE EXCEPTION 'node is still referenced in a menu';
	END IF;

	RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER nodes_trigger_01_delete BEFORE UPDATE ON cms.nodes
	FOR EACH ROW EXECUTE PROCEDURE cms.check_if_deletable();
CREATE TRIGGER nodes_trigger_02_change BEFORE UPDATE ON cms.nodes
	FOR EACH ROW EXECUTE FUNCTION cms.update_changed_column();
CREATE TRIGGER nodes_trigger_03_audit AFTER UPDATE
	ON cms.nodes FOR EACH ROW EXECUTE PROCEDURE
	cms.process_nodes_audit();


CREATE TABLE cms.full_text (
	node bigint NOT NULL,
	locale text NOT NULL,
	document tsvector NOT NULL,
	CONSTRAINT pk_full_text PRIMARY KEY (node, locale),
	CONSTRAINT fk_full_text_nodes FOREIGN KEY (node)
		REFERENCES cms.nodes (node),
	CONSTRAINT ck_full_text_locale CHECK (char_length(locale) <= 32)
);
CREATE INDEX ix_nodes_tsv ON cms.full_text USING GIN(document);


CREATE TABLE cms.url_paths (
	node bigint NOT NULL,
	path text NOT NULL,
	locale text NOT NULL,
	creator bigint NOT NULL,
	editor bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	inactive timestamp with time zone,
	CONSTRAINT pk_url_paths PRIMARY KEY (node, locale, path),
	CONSTRAINT fk_url_paths_nodes FOREIGN KEY (node)
		REFERENCES cms.nodes (node),
	CONSTRAINT fk_url_paths_users_creator FOREIGN KEY (creator)
		REFERENCES cms.users (usr),
	CONSTRAINT fk_url_paths_users_editor FOREIGN KEY (editor)
		REFERENCES cms.users (usr),
	CONSTRAINT ck_url_paths_path CHECK (char_length(path) <= 512),
	CONSTRAINT ck_url_paths_locale CHECK (char_length(locale) <= 32)
);
CREATE UNIQUE INDEX ux_url_paths_path ON cms.url_paths
	USING btree (path);
CREATE UNIQUE INDEX ux_url_paths_locale ON cms.url_paths
	USING btree (node, locale) WHERE (inactive IS NULL);


CREATE TABLE cms.drafts (
	node bigint NOT NULL,
	changed timestamp with time zone NOT NULL,
	editor bigint NOT NULL,
	content jsonb NOT NULL,
	CONSTRAINT pk_drafts PRIMARY KEY (node),
	CONSTRAINT fk_drafts_nodes FOREIGN KEY (node) REFERENCES cms.nodes (node)
);
CREATE FUNCTION cms.process_drafts_audit()
	RETURNS TRIGGER AS $$
BEGIN
	INSERT INTO audit.drafts (
		node, changed, editor, content
	) VALUES (
		OLD.node, OLD.changed, OLD.editor, OLD.content
	);

	RETURN OLD;
EXCEPTION WHEN unique_violation THEN
	RAISE WARNING 'Duplicate drafts audit row skipped. draft: %, changed: %', OLD.node, OLD.changed;
	RETURN NULL;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER drafts_trigger_01_audit AFTER UPDATE
	ON cms.drafts FOR EACH ROW EXECUTE PROCEDURE
	cms.process_drafts_audit();


CREATE TABLE cms.menus (
	menu text NOT NULL,
	description text NOT NULL,
	CONSTRAINT pk_menus PRIMARY KEY (menu),
	CONSTRAINT ck_menus_menu CHECK (char_length(menu) <= 32),
	CONSTRAINT ck_menus_description CHECK (char_length(description) <= 128)
);


CREATE TABLE cms.menu_items (
	item text NOT NULL,
	parent text,
	menu text NOT NULL,
	position integer NOT NULL,
	data jsonb NOT NULL,
	CONSTRAINT pk_menu_items PRIMARY KEY (item),
	CONSTRAINT fk_menu_items_menus FOREIGN KEY (menu)
		REFERENCES cms.menus (menu) ON UPDATE CASCADE,
	CONSTRAINT fk_menu_items_menu_items FOREIGN KEY (parent)
		REFERENCES cms.menu_items (item),
	CONSTRAINT ck_menu_items_item CHECK (char_length(item) <= 64),
	CONSTRAINT ck_menu_items_parent CHECK (char_length(parent) <= 64)
);


CREATE TABLE cms.topics (
	topic bigint GENERATED ALWAYS AS IDENTITY,
	uid text NOT NULL,
	name jsonb NOT NULL,
	color text NOT NULL,
	CONSTRAINT pk_topics PRIMARY KEY (topic),
	CONSTRAINT uc_topics_uid UNIQUE (uid),
	CONSTRAINT ck_topics_uid CHECK (char_length(uid) <= 64),
	CONSTRAINT ck_topics_color CHECK (char_length(color) <= 128)
);


CREATE TABLE cms.tags (
	tag bigint GENERATED ALWAYS AS IDENTITY,
	uid text NOT NULL,
	name jsonb NOT NULL,
	topic bigint NOT NULL,
	CONSTRAINT pk_tags PRIMARY KEY (tag),
	CONSTRAINT uc_tags_uid UNIQUE (uid),
	CONSTRAINT fk_tags_topics FOREIGN KEY (topic)
		REFERENCES cms.topics (topic),
	CONSTRAINT ck_tags_uid CHECK (char_length(uid) <= 64)
);


CREATE TABLE cms.node_tags (
	node bigint NOT NULL,
	tag bigint NOT NULL,
	position integer NOT NULL DEFAULT 0,
	CONSTRAINT pk_node_tags PRIMARY KEY (node, tag),
	CONSTRAINT fk_node_tags_nodes FOREIGN KEY (node)
		REFERENCES cms.nodes (node),
	CONSTRAINT fk_node_tags_tags FOREIGN KEY (tag)
		REFERENCES cms.tags (tag)
);


CREATE TABLE audit.nodes (
	node bigint NOT NULL,
	parent bigint,
	changed timestamp with time zone NOT NULL,
	published boolean NOT NULL,
	hidden boolean NOT NULL,
	locked boolean NOT NULL,
	type bigint NOT NULL,
	editor bigint NOT NULL,
	deleted timestamp with time zone,
	content jsonb NOT NULL,
	CONSTRAINT pk_nodes PRIMARY KEY (node, changed),
	CONSTRAINT fk_audit_nodes FOREIGN KEY (node)
		REFERENCES cms.nodes (node)
);


CREATE TABLE audit.drafts (
	node bigint NOT NULL,
	changed timestamp with time zone NOT NULL,
	editor bigint NOT NULL,
	content jsonb NOT NULL,
	CONSTRAINT pk_drafts PRIMARY KEY (node, changed),
	CONSTRAINT fk_audit_drafts FOREIGN KEY (node)
		REFERENCES cms.drafts (node)
);


CREATE TABLE audit.users (
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
	CONSTRAINT pk_users PRIMARY KEY (usr, changed),
	CONSTRAINT fk_audit_users FOREIGN KEY (usr)
		REFERENCES cms.users (usr)
);

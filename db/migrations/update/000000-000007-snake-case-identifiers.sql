ALTER TABLE cms.userroles RENAME COLUMN userrole TO rolename;
ALTER TABLE cms.users RENAME COLUMN pwhash TO password;
ALTER TABLE cms.users RENAME COLUMN userrole TO rolename;
ALTER TABLE audit.users RENAME COLUMN pwhash TO password;
ALTER TABLE audit.users RENAME COLUMN userrole TO rolename;
ALTER TABLE cms.menuitems RENAME COLUMN displayorder TO position;
ALTER TABLE cms.nodetags RENAME COLUMN sort TO position;

ALTER TABLE cms.userroles RENAME TO roles;
ALTER TABLE cms.authtokens RENAME TO auth_tokens;
ALTER TABLE cms.onetimetokens RENAME TO one_time_tokens;
ALTER TABLE cms.loginsessions RENAME TO login_sessions;
ALTER TABLE cms.fulltext RENAME TO full_text;
ALTER TABLE cms.urlpaths RENAME TO url_paths;
ALTER TABLE cms.menuitems RENAME TO menu_items;
ALTER TABLE cms.nodetags RENAME TO node_tags;

ALTER TABLE cms.roles RENAME CONSTRAINT pk_userroles TO pk_roles;
ALTER TABLE cms.users RENAME CONSTRAINT fk_users_userroles TO fk_users_roles;

ALTER TABLE cms.auth_tokens RENAME CONSTRAINT pk_authtokens TO pk_auth_tokens;
ALTER TABLE cms.auth_tokens RENAME CONSTRAINT fk_authtokens_users TO fk_auth_tokens_users;
ALTER TABLE cms.auth_tokens RENAME CONSTRAINT fk_authtokens_users_creator TO fk_auth_tokens_users_creator;
ALTER TABLE cms.auth_tokens RENAME CONSTRAINT fk_authtokens_users_editor TO fk_auth_tokens_users_editor;
ALTER TABLE cms.auth_tokens RENAME CONSTRAINT uc_authtokens_usr TO uc_auth_tokens_usr;
ALTER TABLE cms.auth_tokens RENAME CONSTRAINT ck_authtokens_token TO ck_auth_tokens_token;

ALTER TABLE cms.one_time_tokens RENAME CONSTRAINT pk_onetimetokens TO pk_one_time_tokens;
ALTER TABLE cms.one_time_tokens RENAME CONSTRAINT fk_onetimetokens_users TO fk_one_time_tokens_users;
ALTER TABLE cms.one_time_tokens RENAME CONSTRAINT ck_ontimetokens_token TO ck_one_time_tokens_token;

ALTER TABLE cms.login_sessions RENAME CONSTRAINT pk_loginsessions TO pk_login_sessions;
ALTER TABLE cms.login_sessions RENAME CONSTRAINT uc_loginsessions_usr TO uc_login_sessions_usr;
ALTER TABLE cms.login_sessions RENAME CONSTRAINT fk_loginsessions_users TO fk_login_sessions_users;
ALTER TABLE cms.login_sessions RENAME CONSTRAINT ck_loginsessions_hash TO ck_login_sessions_hash;

ALTER TABLE cms.full_text RENAME CONSTRAINT pk_fulltext TO pk_full_text;
ALTER TABLE cms.full_text RENAME CONSTRAINT fk_fulltext_nodes TO fk_full_text_nodes;
ALTER TABLE cms.full_text RENAME CONSTRAINT ck_fulltext_locale TO ck_full_text_locale;

ALTER TABLE cms.url_paths RENAME CONSTRAINT pk_urlpaths TO pk_url_paths;
ALTER TABLE cms.url_paths RENAME CONSTRAINT fk_urlpaths_nodes TO fk_url_paths_nodes;
ALTER TABLE cms.url_paths RENAME CONSTRAINT fk_urlpaths_users_creator TO fk_url_paths_users_creator;
ALTER TABLE cms.url_paths RENAME CONSTRAINT fk_urlpaths_users_editor TO fk_url_paths_users_editor;
ALTER TABLE cms.url_paths RENAME CONSTRAINT ck_urlpaths_path TO ck_url_paths_path;
ALTER TABLE cms.url_paths RENAME CONSTRAINT ck_urlpaths_locale TO ck_url_paths_locale;

ALTER TABLE cms.menu_items RENAME CONSTRAINT pk_menuitems TO pk_menu_items;
ALTER TABLE cms.menu_items RENAME CONSTRAINT fk_menuitems_menus TO fk_menu_items_menus;
ALTER TABLE cms.menu_items RENAME CONSTRAINT fk_menuitems_menuitems TO fk_menu_items_menu_items;
ALTER TABLE cms.menu_items RENAME CONSTRAINT ck_menuitems_item TO ck_menu_items_item;
ALTER TABLE cms.menu_items RENAME CONSTRAINT ck_menuitems_parent TO ck_menu_items_parent;

ALTER TABLE cms.node_tags RENAME CONSTRAINT pk_nodetags TO pk_node_tags;
ALTER TABLE cms.node_tags RENAME CONSTRAINT fk_nodetags_nodes TO fk_node_tags_nodes;
ALTER TABLE cms.node_tags RENAME CONSTRAINT fk_nodetags_tags TO fk_node_tags_tags;

ALTER INDEX cms.ux_urlpaths_path RENAME TO ux_url_paths_path;
ALTER INDEX cms.ux_urlpaths_locale RENAME TO ux_url_paths_locale;

ALTER TRIGGER authtokens_trigger_01_change ON cms.auth_tokens RENAME TO auth_tokens_trigger_01_change;

CREATE OR REPLACE FUNCTION cms.process_users_audit()
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

CREATE OR REPLACE FUNCTION cms.check_if_deletable()
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

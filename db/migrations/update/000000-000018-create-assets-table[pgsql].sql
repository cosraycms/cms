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

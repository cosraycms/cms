CREATE TABLE /*:cms.prefix:*/test_plugin_items (
	item integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
	usr integer NOT NULL REFERENCES /*:cms.prefix:*/users (usr)
);

CREATE TABLE /*:test-plugin.prefix:*/items (
	item integer GENERATED ALWAYS AS IDENTITY,
	usr bigint NOT NULL,
	CONSTRAINT /*:test-plugin.obj:*/pk_items PRIMARY KEY (item),
	CONSTRAINT /*:test-plugin.obj:*/fk_items_users FOREIGN KEY (usr)
		REFERENCES /*:cms.prefix:*/users (usr)
);
CREATE INDEX /*:test-plugin.obj:*/ix_items_usr ON /*:test-plugin.prefix:*/items (usr);

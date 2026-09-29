-- Manual orders. A row places a node either among the children of
-- `parent` or at the top level of the collection with the handle
-- `collection`; every scope keeps its own order, so one node can hold a
-- position in several. Positions are structure rather than content: they
-- are written outside `nodes`, so reordering creates no history row and
-- leaves `changed` alone. Collections live in code, hence the plain
-- handle without a foreign key.

CREATE TABLE /*:cms.prefix:*/node_positions (
	node bigint NOT NULL,
	parent bigint,
	collection text,
	position integer NOT NULL,
	CONSTRAINT /*:cms.obj:*/fk_node_positions_nodes FOREIGN KEY (node)
		REFERENCES /*:cms.prefix:*/nodes (node) ON DELETE CASCADE,
	CONSTRAINT /*:cms.obj:*/fk_node_positions_parent FOREIGN KEY (parent)
		REFERENCES /*:cms.prefix:*/nodes (node) ON DELETE CASCADE,
	CONSTRAINT /*:cms.obj:*/ck_node_positions_scope CHECK ((parent IS NULL) <> (collection IS NULL))
);
CREATE UNIQUE INDEX /*:cms.obj:*/ux_node_positions_parent
	ON /*:cms.prefix:*/node_positions (parent, node) WHERE parent IS NOT NULL;
CREATE UNIQUE INDEX /*:cms.obj:*/ux_node_positions_collection
	ON /*:cms.prefix:*/node_positions (collection, node) WHERE collection IS NOT NULL;
CREATE INDEX /*:cms.obj:*/ix_node_positions_node ON /*:cms.prefix:*/node_positions (node);

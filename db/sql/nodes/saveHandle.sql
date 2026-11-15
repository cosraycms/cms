-- Returns no row when the node already has this handle.
INSERT INTO /*:cms.prefix:*/handles (
	node,
	handle,
	creator
) VALUES (
	:node,
	:handle,
	:editor
)

ON CONFLICT (node) DO UPDATE SET
	handle = :handle
WHERE
	/*:cms.prefix:*/handles.node = :node
	AND /*:cms.prefix:*/handles.handle IS DISTINCT FROM :handle

RETURNING node;

-- Returns no row when the node already has this handle.
INSERT INTO /*:cms.prefix:*/node_handles (
	node,
	handle,
	creator,
	editor
) VALUES (
	:node,
	:handle,
	:editor,
	:editor
)

ON CONFLICT (node) DO UPDATE SET
	handle = :handle,
	editor = :editor
WHERE
	/*:cms.prefix:*/node_handles.node = :node
	AND /*:cms.prefix:*/node_handles.handle IS DISTINCT FROM :handle

RETURNING node;

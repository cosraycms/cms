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

RETURNING node;

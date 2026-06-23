import type { Attrs, MarkType, NodeType } from 'prosemirror-model';
import { type EditorState, NodeSelection } from 'prosemirror-state';

export function isMarkActive(state: EditorState, type: MarkType): boolean {
	const { from, $from, to, empty } = state.selection;
	if (empty) {
		return !!type.isInSet(state.storedMarks || $from.marks());
	}
	return state.doc.rangeHasMark(from, to, type);
}

export function getMarkAttributes(state: EditorState, type: MarkType): Attrs | null {
	const { from, $from, to, empty } = state.selection;

	if (empty) {
		const marks = state.storedMarks || $from.marks();
		const mark = type.isInSet(marks);
		return mark ? mark.attrs : null;
	}

	let attrs: Attrs | null = null;
	state.doc.nodesBetween(from, to, (node) => {
		if (attrs !== null) return false;
		const mark = type.isInSet(node.marks);
		if (mark) {
			attrs = mark.attrs;
			return false;
		}
	});
	return attrs;
}

export function isNodeActive(state: EditorState, type: NodeType, attrs?: Attrs): boolean {
	const { $from, to } = state.selection;

	// Walk up from the selection anchor to find a matching ancestor
	for (let depth = $from.depth; depth >= 0; depth--) {
		const node = $from.node(depth);
		if (node.type === type) {
			if (!attrs) return true;
			return Object.entries(attrs).every(([key, value]) => node.attrs[key] === value);
		}
	}

	// For node selections, also check the selected node itself
	if (state.selection instanceof NodeSelection) {
		const node = state.selection.node;
		if (node.type === type) {
			if (!attrs) return true;
			return Object.entries(attrs).every(([key, value]) => node.attrs[key] === value);
		}
	}

	return false;
}

export function getBlockAttributes(state: EditorState, type: NodeType): Attrs | null {
	const { $from } = state.selection;

	for (let depth = $from.depth; depth >= 0; depth--) {
		const node = $from.node(depth);
		if (node.type === type) {
			return node.attrs;
		}
	}
	return null;
}

export function getActiveTextAlign(state: EditorState): string | null {
	const { $from } = state.selection;

	for (let depth = $from.depth; depth >= 0; depth--) {
		const node = $from.node(depth);
		if (node.attrs.textAlign) {
			return node.attrs.textAlign;
		}
	}
	return null;
}

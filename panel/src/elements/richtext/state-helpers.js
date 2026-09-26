/** @import { Attrs, MarkType, NodeType } from 'prosemirror-model' */
/** @import { EditorState } from 'prosemirror-state' */

import { NodeSelection } from 'prosemirror-state';

/**
 * @param {EditorState} state
 * @param {MarkType} type
 * @returns {boolean}
 */
export function isMarkActive(state, type) {
	const { from, $from, to, empty } = state.selection;
	if (empty) {
		return !!type.isInSet(state.storedMarks || $from.marks());
	}
	return state.doc.rangeHasMark(from, to, type);
}

/**
 * @param {EditorState} state
 * @param {MarkType} type
 * @returns {Attrs | null}
 */
export function getMarkAttributes(state, type) {
	const { from, $from, to, empty } = state.selection;

	if (empty) {
		const marks = state.storedMarks || $from.marks();
		const mark = type.isInSet(marks);
		return mark ? mark.attrs : null;
	}

	/** @type {Attrs | null} */
	let attrs = null;
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

/**
 * @param {EditorState} state
 * @param {NodeType} type
 * @param {Attrs} [attrs]
 * @returns {boolean}
 */
export function isNodeActive(state, type, attrs) {
	const { $from, to } = state.selection;

	for (let depth = $from.depth; depth >= 0; depth--) {
		const node = $from.node(depth);
		if (node.type === type) {
			if (!attrs) return true;
			return Object.entries(attrs).every(([key, value]) => node.attrs[key] === value);
		}
	}

	if (state.selection instanceof NodeSelection) {
		const node = state.selection.node;
		if (node.type === type) {
			if (!attrs) return true;
			return Object.entries(attrs).every(([key, value]) => node.attrs[key] === value);
		}
	}

	return false;
}

/**
 * @param {EditorState} state
 * @param {NodeType} type
 * @returns {Attrs | null}
 */
export function getBlockAttributes(state, type) {
	const { $from } = state.selection;

	for (let depth = $from.depth; depth >= 0; depth--) {
		const node = $from.node(depth);
		if (node.type === type) {
			return node.attrs;
		}
	}
	return null;
}

/**
 * @param {EditorState} state
 * @returns {string | null}
 */
export function getActiveTextAlign(state) {
	const { $from } = state.selection;

	for (let depth = $from.depth; depth >= 0; depth--) {
		const node = $from.node(depth);
		if (node.attrs.textAlign) {
			return node.attrs.textAlign;
		}
	}
	return null;
}

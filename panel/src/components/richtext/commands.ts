import type { Attrs, MarkType, NodeType } from 'prosemirror-model';
import type { Command, EditorState } from 'prosemirror-state';
import { toggleMark } from 'prosemirror-commands';
import { liftListItem, wrapInList } from 'prosemirror-schema-list';
import { schema } from './schema';

export function setTextAlign(alignment: string): Command {
	return (state, dispatch) => {
		const { from, to } = state.selection;
		if (!dispatch) return true;
		const tr = state.tr;
		state.doc.nodesBetween(from, to, (node, pos) => {
			if (node.type === schema.nodes.paragraph || node.type === schema.nodes.heading) {
				tr.setNodeMarkup(pos, undefined, { ...node.attrs, textAlign: alignment });
			}
		});
		dispatch(tr.scrollIntoView());
		return true;
	};
}

export function unsetTextAlign(): Command {
	return (state, dispatch) => {
		const { from, to } = state.selection;
		if (!dispatch) return true;
		const tr = state.tr;
		state.doc.nodesBetween(from, to, (node, pos) => {
			if (node.type === schema.nodes.paragraph || node.type === schema.nodes.heading) {
				tr.setNodeMarkup(pos, undefined, { ...node.attrs, textAlign: null });
			}
		});
		dispatch(tr.scrollIntoView());
		return true;
	};
}

export function setParagraphClass(cls: string): Command {
	return (state, dispatch) => {
		const { from, to } = state.selection;
		if (!dispatch) return true;
		const tr = state.tr;
		state.doc.nodesBetween(from, to, (node, pos) => {
			if (node.type === schema.nodes.paragraph) {
				tr.setNodeMarkup(pos, undefined, { ...node.attrs, class: cls });
			} else if (node.type === schema.nodes.heading) {
				tr.setNodeMarkup(pos, schema.nodes.paragraph, {
					class: cls,
					textAlign: node.attrs.textAlign,
				});
			}
		});
		dispatch(tr.scrollIntoView());
		return true;
	};
}

export function insertHorizontalRule(): Command {
	return (state, dispatch) => {
		if (!dispatch) return true;
		const { $to } = state.selection;
		const pos = $to.after($to.depth);
		const tr = state.tr.insert(pos, schema.nodes.horizontalRule.create());
		dispatch(tr.scrollIntoView());
		return true;
	};
}

// The range a link edit should target: the active selection when there is
// one, otherwise the full extent of the link mark under a collapsed cursor.
// Editing a link commonly leaves the cursor inside it with nothing selected,
// so without this expansion re-picking a target would be a silent no-op.
function linkRange(state: EditorState): { from: number; to: number } | null {
	const { from, to, empty } = state.selection;
	if (!empty) return { from, to };

	const $pos = state.selection.$from;
	const link = schema.marks.link.isInSet($pos.marks());
	if (!link) return null;

	const parent = $pos.parent;
	const parentStart = $pos.start();
	let start = $pos.pos;
	let end = $pos.pos;

	// Extend across the adjacent nodes carrying the same link mark.
	parent.forEach((child, offset) => {
		if (!link.isInSet(child.marks)) return;
		const childStart = parentStart + offset;
		const childEnd = childStart + child.nodeSize;
		if (childStart <= $pos.pos && childEnd >= $pos.pos) {
			start = childStart;
			end = childEnd;
		}
	});

	return { from: start, to: end };
}

export function setLink(attrs: Attrs): Command {
	return (state, dispatch) => {
		const range = linkRange(state);
		if (!range) return false;
		if (!dispatch) return true;
		const { from, to } = range;
		const mark = schema.marks.link.create(attrs);
		// Strip the old link first so switching kind (href/node/asset) never
		// leaves a stale carrier attr behind.
		dispatch(
			state.tr.removeMark(from, to, schema.marks.link).addMark(from, to, mark).scrollIntoView(),
		);
		return true;
	};
}

export function unsetLink(): Command {
	return (state, dispatch) => {
		const range = linkRange(state);
		if (!range) return false;
		if (!dispatch) return true;
		const { from, to } = range;
		dispatch(state.tr.removeMark(from, to, schema.marks.link).scrollIntoView());
		return true;
	};
}

export function clearMarks(): Command {
	return (state, dispatch) => {
		const { from, to, empty } = state.selection;
		if (empty) return false;
		if (!dispatch) return true;
		const tr = state.tr;
		for (const markName of Object.keys(schema.marks)) {
			tr.removeMark(from, to, schema.marks[markName]);
		}
		dispatch(tr.scrollIntoView());
		return true;
	};
}

export function clearNodes(): Command {
	return (state, dispatch) => {
		const { from, to } = state.selection;
		if (!dispatch) return true;
		const tr = state.tr;
		state.doc.nodesBetween(from, to, (node, pos) => {
			if (node.isBlock && node.type !== schema.nodes.doc && node.type !== schema.nodes.paragraph) {
				if (node.type === schema.nodes.listItem) return;
				if (node.isTextblock) {
					tr.setNodeMarkup(pos, schema.nodes.paragraph, {
						class: 'default',
						textAlign: null,
					});
				}
			}
		});
		dispatch(tr.scrollIntoView());
		return true;
	};
}

export function insertHardBreak(): Command {
	return (state, dispatch) => {
		if (!dispatch) return true;
		dispatch(state.tr.replaceSelectionWith(schema.nodes.hardBreak.create()).scrollIntoView());
		return true;
	};
}

export function toggleBold(): Command {
	return toggleMark(schema.marks.bold);
}

export function toggleItalic(): Command {
	return toggleMark(schema.marks.italic);
}

export function toggleStrike(): Command {
	return toggleMark(schema.marks.strike);
}

export function toggleCode(): Command {
	return toggleMark(schema.marks.code);
}

export function toggleSubscript(): Command {
	return toggleMark(schema.marks.subscript);
}

export function toggleSuperscript(): Command {
	return toggleMark(schema.marks.superscript);
}

export function setStyle(cls: string): Command {
	return (state, dispatch) => {
		const { from, to, empty } = state.selection;
		if (empty) return false;
		if (!dispatch) return true;
		const mark = schema.marks.style.create({ class: cls });
		dispatch(state.tr.addMark(from, to, mark).scrollIntoView());
		return true;
	};
}

export function unsetStyle(): Command {
	return (state, dispatch) => {
		const { from, to, empty } = state.selection;
		if (empty) return false;
		if (!dispatch) return true;
		dispatch(state.tr.removeMark(from, to, schema.marks.style).scrollIntoView());
		return true;
	};
}

export function insertImage(uid: string): Command {
	return (state, dispatch) => {
		if (!dispatch) return true;
		const node = schema.nodes.image.create({ uid });
		dispatch(state.tr.replaceSelectionWith(node).scrollIntoView());
		return true;
	};
}

export function toggleBulletList(): Command {
	return (state, dispatch, view) => {
		const { bulletList, listItem } = schema.nodes;
		if (state.selection.$from.node(-1)?.type === bulletList) {
			return liftListItem(listItem)(state, dispatch);
		}
		return wrapInList(bulletList)(state, dispatch);
	};
}

export function toggleOrderedList(): Command {
	return (state, dispatch, view) => {
		const { orderedList, listItem } = schema.nodes;
		if (state.selection.$from.node(-1)?.type === orderedList) {
			return liftListItem(listItem)(state, dispatch);
		}
		return wrapInList(orderedList)(state, dispatch);
	};
}

export function toggleBlockquote(): Command {
	return (state, dispatch) => {
		const { blockquote } = schema.nodes;
		const { $from } = state.selection;

		for (let depth = $from.depth; depth >= 0; depth--) {
			if ($from.node(depth).type === blockquote) {
				// Already in a blockquote: lift out
				if (!dispatch) return true;
				const range = $from.blockRange(state.selection.$to);
				if (!range) return false;
				const tr = state.tr.lift(range, range.depth - 1);
				dispatch(tr.scrollIntoView());
				return true;
			}
		}

		// Not in a blockquote: wrap
		const range = state.selection.$from.blockRange(state.selection.$to);
		if (!range) return false;
		if (!dispatch) return true;
		const tr = state.tr.wrap(range, [{ type: blockquote }]);
		dispatch(tr.scrollIntoView());
		return true;
	};
}

export function setHeading(level: number): Command {
	return (state, dispatch) => {
		const { from, to } = state.selection;
		if (!dispatch) return true;
		const tr = state.tr;
		state.doc.nodesBetween(from, to, (node, pos) => {
			if (node.isTextblock) {
				const isAlreadyHeading = node.type === schema.nodes.heading && node.attrs.level === level;
				if (isAlreadyHeading) {
					tr.setNodeMarkup(pos, schema.nodes.paragraph);
				} else {
					tr.setNodeMarkup(pos, schema.nodes.heading, {
						level,
						textAlign: node.attrs.textAlign || null,
					});
				}
			}
		});
		dispatch(tr.scrollIntoView());
		return true;
	};
}

export function setParagraph(): Command {
	return (state, dispatch) => {
		const { from, to } = state.selection;
		if (!dispatch) return true;
		const tr = state.tr;
		state.doc.nodesBetween(from, to, (node, pos) => {
			if (node.isTextblock && node.type !== schema.nodes.paragraph) {
				tr.setNodeMarkup(pos, schema.nodes.paragraph, {
					class: 'default',
					textAlign: node.attrs.textAlign || null,
				});
			}
		});
		dispatch(tr.scrollIntoView());
		return true;
	};
}

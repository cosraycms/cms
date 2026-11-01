// The editor of a caption: one line of text in bold, italic or struck through,
// in the same document format as a richtext field. Its own small schema keeps
// everything else out, also when pasting.

/** @import { Command, EditorState as State } from 'prosemirror-state' */
/** @import { Node as PmNode } from 'prosemirror-model' */
/** @import { RichtextDoc } from '../../types/data' */

import { baseKeymap, toggleMark } from 'prosemirror-commands';
import { history, redo, undo } from 'prosemirror-history';
import { keymap } from 'prosemirror-keymap';
import { Fragment, Schema, Slice } from 'prosemirror-model';
import { EditorState } from 'prosemirror-state';
import { EditorView } from 'prosemirror-view';
import { icon } from '../../lib/icons.js';
import { docText } from '../../lib/inline-doc.js';
import { __ } from '../../lib/locale.js';
import { schema as richtext } from './schema.js';

const schema = new Schema({
	nodes: {
		doc: { content: 'paragraph' },
		paragraph: {
			content: 'text*',
			parseDOM: [{ tag: 'p' }],
			toDOM: () => ['p', 0],
		},
		text: {},
	},
	marks: {
		bold: richtext.marks.bold.spec,
		italic: richtext.marks.italic.spec,
		strike: richtext.marks.strike.spec,
	},
});

const TOOLS = [
	{ mark: schema.marks.bold, name: 'type-bold', label: 'richtext:bold' },
	{ mark: schema.marks.italic, name: 'type-italic', label: 'richtext:italic' },
	{ mark: schema.marks.strike, name: 'type-strikethrough', label: 'richtext:strikethrough' },
];

/**
 * @param {RichtextDoc | string | null | undefined} value
 * @returns {PmNode}
 */
function toPm(value) {
	const paragraph = (/** @type {string} */ text) =>
		schema.nodes.doc.create(
			null,
			schema.nodes.paragraph.create(null, text ? schema.text(text) : undefined),
		);

	if (!value || typeof value === 'string') return paragraph(value ?? '');

	try {
		const doc = schema.nodeFromJSON(value);
		doc.check();

		return doc;
	} catch {
		return paragraph(docText(value));
	}
}

/**
 * All of a paste on one line.
 *
 * @param {Slice} slice
 * @returns {Slice}
 */
function flatten(slice) {
	/** @type {PmNode[]} */
	const nodes = [];

	slice.content.forEach((block) => {
		if (nodes.length > 0) nodes.push(schema.text(' '));

		if (block.isText) nodes.push(block);
		else block.forEach((node) => nodes.push(node));
	});

	return new Slice(Fragment.fromArray(nodes), 0, 0);
}

/**
 * @typedef {object} InlineEditorOptions
 * @property {RichtextDoc | string | null | undefined} value
 * @property {string} label
 * @property {boolean} [readonly]
 * @property {(doc: RichtextDoc | null) => void} change Null once nothing is left.
 * @property {() => void} [focus]
 * @property {() => void} [blur]
 */

/**
 * @param {InlineEditorOptions} options
 * @returns {{ element: HTMLElement, field: HTMLElement, set: (value: RichtextDoc | string | null | undefined) => void, placeholder: (text: string) => void, destroy: () => void }}
 */
export function inlineEditor({ value, label, readonly = false, change, focus, blur }) {
	const element = document.createElement('div');
	element.className = 'cms-inline-editor';
	const hint = document.createElement('span');
	hint.className = 'placeholder';
	const toolbar = document.createElement('div');
	toolbar.className = 'tools';
	/** @type {{ button: HTMLButtonElement, mark: import('prosemirror-model').MarkType }[]} */
	const buttons = [];

	const start = EditorState.create({
		doc: toPm(value),
		plugins: [
			history(),
			keymap({
				'Mod-b': toggleMark(schema.marks.bold),
				'Mod-i': toggleMark(schema.marks.italic),
				'Mod-Shift-x': toggleMark(schema.marks.strike),
				'Mod-z': undo,
				'Mod-y': redo,
				'Mod-Shift-z': redo,
				Enter: () => true,
				'Shift-Enter': () => true,
			}),
			keymap(baseKeymap),
		],
	});

	/** @param {State} state */
	function refresh(state) {
		hint.hidden = state.doc.textContent !== '';

		for (const { button, mark } of buttons) {
			const { from, $from, to, empty } = state.selection;
			const active = empty
				? Boolean(mark.isInSet(state.storedMarks ?? $from.marks()))
				: state.doc.rangeHasMark(from, to, mark);
			button.setAttribute('aria-pressed', String(active));
		}
	}

	const view = new EditorView(element, {
		state: start,
		editable: () => !readonly,
		transformPasted: flatten,
		clipboardTextParser: (text) =>
			new Slice(Fragment.from(schema.text(text.replace(/\s+/g, ' ') || ' ')), 0, 0),
		attributes: {
			role: 'textbox',
			'aria-label': label,
			'aria-multiline': 'false',
		},
		handleDOMEvents: {
			focus: () => {
				element.classList.add('is-focused');
				focus?.();

				return false;
			},
			blur: () => {
				element.classList.remove('is-focused');
				blur?.();

				return false;
			},
		},
		dispatchTransaction(transaction) {
			const state = view.state.apply(transaction);
			view.updateState(state);
			refresh(state);

			if (transaction.docChanged) {
				const filled = state.doc.textContent.trim() !== '';
				change(filled ? /** @type {RichtextDoc} */ (state.doc.toJSON()) : null);
			}
		},
	});

	element.prepend(hint);

	if (!readonly) {
		for (const { mark, name, label: tool } of TOOLS) {
			const button = document.createElement('button');
			button.type = 'button';
			button.title = __(tool);
			button.setAttribute('aria-label', __(tool));
			button.innerHTML = icon(name);
			// A click must leave the selection it acts on.
			button.addEventListener('mousedown', (event) => event.preventDefault());
			button.addEventListener('click', () => {
				/** @type {Command} */ (toggleMark(mark))(view.state, view.dispatch);
				view.focus();
			});
			toolbar.append(button);
			buttons.push({ button, mark });
		}

		element.append(toolbar);
	}

	refresh(view.state);

	return {
		element,
		field: view.dom,
		set(next) {
			const state = EditorState.create({ doc: toPm(next), plugins: view.state.plugins });
			view.updateState(state);
			refresh(state);
		},
		placeholder(text) {
			hint.textContent = text;
		},
		destroy() {
			view.destroy();
		},
	};
}

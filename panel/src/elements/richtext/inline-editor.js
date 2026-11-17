// The editor of a caption: the one-line richtext editor of a RichLine with
// the caption's vocabulary, bold, italic and struck through, marked through
// a bubble over the selection.

/** @import { Command } from 'prosemirror-state' */
/** @import { RichtextDoc } from '../../types/data' */

import { icon } from '../../lib/icons.js';
import { docText } from '../../lib/inline-doc.js';
import { __ } from '../../lib/locale.js';
import { toggleBold, toggleItalic, toggleStrike } from './commands.js';
import createEditor from './editor.js';
import { schema } from './schema.js';
import { isMarkActive } from './state-helpers.js';
import { vocabulary } from './vocabulary.js';

const TOOLS = [
	{ mark: schema.marks.bold, command: toggleBold, name: 'type-bold', label: 'richtext:bold' },
	{
		mark: schema.marks.italic,
		command: toggleItalic,
		name: 'type-italic',
		label: 'richtext:italic',
	},
	{
		mark: schema.marks.strike,
		command: toggleStrike,
		name: 'type-strikethrough',
		label: 'richtext:strikethrough',
	},
];

/**
 * A stored caption as a document: plain text from before captions had marks
 * becomes its one paragraph.
 *
 * @param {RichtextDoc | string | null | undefined} value
 * @returns {RichtextDoc | null}
 */
function read(value) {
	if (typeof value !== 'string') return value ?? null;

	return {
		type: 'doc',
		content: [{ type: 'paragraph', content: value ? [{ type: 'text', text: value }] : [] }],
	};
}

/**
 * The paragraph's default attributes are no part of a caption.
 *
 * @param {RichtextDoc} doc
 * @returns {RichtextDoc}
 */
function canonical(doc) {
	return {
		...doc,
		content: (doc.content ?? []).map(({ attrs, ...block }) => {
			const own = Object.fromEntries(
				Object.entries(attrs ?? {}).filter(
					([key, value]) => key !== 'class' || value !== 'default',
				),
			);

			return Object.keys(own).length > 0 ? { ...block, attrs: own } : block;
		}),
	};
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
	element.className = 'cms-richtext is-line has-bubble';
	const content = document.createElement('div');
	content.className = 'cms-richtext-content';
	element.append(content);
	/** @type {HTMLElement | undefined} */
	let bubble;
	/** @type {(() => void)[]} */
	const updates = [];

	if (!readonly) {
		bubble = document.createElement('div');
		bubble.className = 'cms-richtext-bubble';
		element.prepend(bubble);
	}

	const editor = createEditor({
		element: content,
		content: read(value),
		vocabulary: vocabulary(['bold', 'italic', 'strike']),
		line: true,
		label,
		bubbleElement: bubble,
		editable: () => !readonly,
		onUpdate: (doc) => change(docText(doc) === '' ? null : canonical(doc)),
		onStateChange: () => {
			for (const update of updates) update();
		},
	});

	for (const { mark, command, name, label: tool } of bubble ? TOOLS : []) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'cms-richtext-toolbar-btn';
		button.title = __(tool);
		button.setAttribute('aria-label', __(tool));
		button.innerHTML = icon(name);
		// A click must leave the selection it acts on.
		button.addEventListener('mousedown', (event) => event.preventDefault());
		button.addEventListener('click', () => editor.run(/** @type {Command} */ (command())));
		bubble?.append(button);
		updates.push(() => button.classList.toggle('is-active', isMarkActive(editor.view.state, mark)));
	}

	for (const update of updates) update();

	element.addEventListener('focusin', () => focus?.());
	element.addEventListener('focusout', (event) => {
		if (!(event.relatedTarget instanceof Node) || !element.contains(event.relatedTarget)) blur?.();
	});

	return {
		element,
		field: editor.view.dom,
		set(next) {
			editor.load(read(next));
		},
		placeholder(text) {
			editor.placeholder(text);
		},
		destroy() {
			editor.destroy();
		},
	};
}

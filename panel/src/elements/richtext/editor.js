/** @import { Command } from 'prosemirror-state' */
/** @import { Node } from 'prosemirror-model' */
/** @import { RichtextDoc } from './format.js' */
/** @import { Vocabulary } from './vocabulary.js' */

import { Slice } from 'prosemirror-model';
import { EditorState, Plugin } from 'prosemirror-state';
import { Decoration, DecorationSet, EditorView } from 'prosemirror-view';
import { history } from 'prosemirror-history';
import { baseKeymap } from 'prosemirror-commands';
import { keymap } from 'prosemirror-keymap';
import { dropCursor } from 'prosemirror-dropcursor';
import { gapCursor } from 'prosemirror-gapcursor';
import { schema, serializer } from './schema.js';
import { buildKeymap, buildInputRules } from './keymap.js';
import { bubbleMenu } from './bubble-menu.js';
import { flatten, lineText, linePlugins, toLine } from './line.js';
import { clean } from './vocabulary.js';
import { docToPm, parseHtml, pmToDoc } from './format.js';

/**
 * The internal editor driver seam: components talk documents in the
 * cosray richtext format plus the commands API — everything
 * ProseMirror-specific stays behind this module (and schema/format/
 * commands). A future editor swap replaces the driver, not the
 * callers.
 *
 * @typedef {object} CmsEditor
 * @property {EditorView} view
 * @property {(command: Command) => void} run
 * @property {(doc: RichtextDoc | null) => void} load Replaces the document, with a fresh history.
 * @property {(text: string) => void} placeholder
 * @property {() => RichtextDoc} getDoc
 * @property {() => string} getHTML
 * @property {(html: string) => void} setContent
 * @property {() => void} destroy
 */

/**
 * @typedef {object} EditorOptions
 * @property {HTMLElement} element
 * @property {RichtextDoc | null} content
 * @property {(doc: RichtextDoc) => void} onUpdate
 * @property {(state: EditorState) => void} onStateChange
 * @property {Vocabulary} vocabulary What typing, pastes and drops may bring in.
 * @property {boolean} [line] One paragraph of text: Enter does nothing and a paste lands on the line.
 * @property {string} [label] The accessible name, where no label element names the editor.
 * @property {boolean} [required] Tells assistive technology that the field needs content.
 * @property {string} [describedBy] The id of the field's description.
 * @property {HTMLElement} [bubbleElement] The menu over a selection, for a field without a toolbar.
 * @property {(uid: string) => string | null} [assetUrl] Resolve an asset uid to a display URL for inline images.
 * @property {() => boolean} [editable] Whether the document takes input; the view stays selectable
 *     either way, so a read-only document can still be read and copied. Absent means editable.
 * @property {string} [placeholder] Shown on a blank document's empty line; none when empty.
 */

/**
 * @param {EditorState} state
 * @returns {string}
 */
function serializeContent(state) {
	const fragment = serializer.serializeFragment(state.doc.content);
	const container = document.createElement('div');
	container.appendChild(fragment);
	return container.innerHTML;
}

/**
 * Nothing but one empty line, of whatever kind.
 *
 * @param {Node} doc
 * @returns {boolean}
 */
function blank(doc) {
	const first = doc.firstChild;

	return doc.childCount === 1 && first !== null && first.isTextblock && first.content.size === 0;
}

/**
 * The placeholder sits on the empty line itself, so it takes that line's
 * font and place; the stylesheet shows it through the attribute.
 *
 * @param {{ text: string }} hint Read on every update, so it can change.
 * @returns {Plugin}
 */
function placeholderPlugin(hint) {
	return new Plugin({
		props: {
			attributes: () => {
				/** @type {Record<string, string>} */
				const attributes = {};

				if (hint.text) attributes['aria-placeholder'] = hint.text;

				return attributes;
			},
			decorations(state) {
				return hint.text && blank(state.doc)
					? DecorationSet.create(state.doc, [
							Decoration.node(0, /** @type {Node} */ (state.doc.firstChild).nodeSize, {
								class: 'is-placeholder',
								'data-placeholder': hint.text,
							}),
						])
					: null;
			},
		},
	});
}

/**
 * @param {EditorOptions} options
 * @returns {CmsEditor}
 */
export default function createEditor(options) {
	const {
		element,
		content,
		onUpdate,
		onStateChange,
		vocabulary,
		line = false,
		label,
		required = false,
		describedBy,
		bubbleElement,
		assetUrl,
		editable,
		placeholder,
	} = options;

	const hint = { text: placeholder ?? '' };
	/** @param {RichtextDoc | null} doc */
	const read = (doc) => (line ? toLine(docToPm(doc)) : docToPm(doc));
	const rules = buildInputRules(vocabulary);
	const plugins = [
		...(line ? linePlugins() : []),
		...(rules ? [rules] : []),
		buildKeymap(vocabulary),
		keymap(baseKeymap),
		history(),
		dropCursor(),
		gapCursor(),
	];

	if (bubbleElement) {
		plugins.push(bubbleMenu(bubbleElement));
	}

	plugins.push(placeholderPlugin(hint));

	if (label || required || describedBy) {
		// Without a role, a contenteditable is generic, and its name goes unannounced.
		/** @type {Record<string, string>} */
		const attributes = { role: 'textbox' };
		if (!line) attributes['aria-multiline'] = 'true';
		if (label) attributes['aria-label'] = label;
		if (required) attributes['aria-required'] = 'true';
		if (describedBy) attributes['aria-describedby'] = describedBy;
		plugins.push(new Plugin({ props: { attributes } }));
	}

	const state = EditorState.create({
		doc: read(content),
		schema,
		plugins,
	});

	const view = new EditorView(element, {
		state,
		editable,
		transformPasted: (slice) =>
			line ? flatten(clean(slice, vocabulary)) : clean(slice, vocabulary),
		...(line ? { clipboardTextParser: lineText } : {}),
		nodeViews: {
			image(node) {
				const dom = document.createElement('img');
				dom.setAttribute('data-uid', node.attrs.uid);
				const url = assetUrl?.(node.attrs.uid) ?? null;

				if (url) {
					dom.src = url;
				} else {
					dom.alt = `[${node.attrs.uid}]`;
				}

				return { dom };
			},
		},
		dispatchTransaction(tr) {
			const newState = view.state.apply(tr);
			view.updateState(newState);
			onStateChange(newState);
			if (tr.docChanged) {
				onUpdate(pmToDoc(newState.doc));
			}
		},
	});

	onStateChange(view.state);

	return {
		view,

		run(command) {
			view.focus();
			command(view.state, view.dispatch, view);
		},

		load(doc) {
			const state = EditorState.create({ doc: read(doc), plugins: view.state.plugins });
			view.updateState(state);
			onStateChange(state);
		},

		placeholder(text) {
			hint.text = text;
			view.dispatch(view.state.tr);
		},

		getDoc() {
			return pmToDoc(view.state.doc);
		},

		getHTML() {
			return serializeContent(view.state);
		},

		setContent(html) {
			const { content } = clean(new Slice(parseHtml(html).content, 0, 0), vocabulary);
			const tr = view.state.tr.replaceWith(
				0,
				view.state.doc.content.size,
				content.size > 0 ? content : schema.nodes.paragraph.create(),
			);
			view.dispatch(tr);
		},

		destroy() {
			view.destroy();
		},
	};
}

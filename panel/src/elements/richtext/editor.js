/** @import { Command } from 'prosemirror-state' */
/** @import { Node } from 'prosemirror-model' */
/** @import { RichtextDoc } from './format.js' */

import { EditorState, Plugin } from 'prosemirror-state';
import { Decoration, DecorationSet, EditorView } from 'prosemirror-view';
import { history } from 'prosemirror-history';
import { baseKeymap } from 'prosemirror-commands';
import { keymap } from 'prosemirror-keymap';
import { dropCursor } from 'prosemirror-dropcursor';
import { gapCursor } from 'prosemirror-gapcursor';
import { schema, parser, serializer } from './schema.js';
import { buildKeymap, buildInputRules } from './keymap.js';
import { bubbleMenu } from './bubble-menu.js';
import { docToPm, pmToDoc } from './format.js';

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
 * @property {'default' | 'inline'} mode
 * @property {HTMLElement} [bubbleElement]
 * @property {(uid: string) => string | null} [assetUrl] Resolve an asset uid to a display URL for inline images.
 * @property {() => boolean} [editable] Whether the document takes input; the view stays selectable
 *     either way, so a read-only document can still be read and copied. Absent means editable.
 * @property {string} [placeholder] Shown on a blank document's empty line; none when empty.
 */

/** @param {string} html */
function parseContent(html) {
	const container = document.createElement('div');
	container.innerHTML = html;
	return parser.parse(container);
}

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
 * @param {string} text
 * @returns {Plugin}
 */
function placeholderPlugin(text) {
	return new Plugin({
		props: {
			attributes: { 'aria-placeholder': text },
			decorations(state) {
				return blank(state.doc)
					? DecorationSet.create(state.doc, [
							Decoration.node(0, /** @type {Node} */ (state.doc.firstChild).nodeSize, {
								class: 'is-placeholder',
								'data-placeholder': text,
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
		mode,
		bubbleElement,
		assetUrl,
		editable,
		placeholder,
	} = options;

	const plugins = [
		buildInputRules(),
		buildKeymap(),
		keymap(baseKeymap),
		history(),
		dropCursor(),
		gapCursor(),
	];

	if (mode === 'inline' && bubbleElement) {
		plugins.push(bubbleMenu(bubbleElement));
	}

	if (placeholder) {
		plugins.push(placeholderPlugin(placeholder));
	}

	const state = EditorState.create({
		doc: docToPm(content),
		schema,
		plugins,
	});

	const view = new EditorView(element, {
		state,
		editable,
		nodeViews: {
			image(node) {
				const dom = document.createElement('img');
				dom.className = 'cms-richtext-image';
				dom.setAttribute('data-uid', node.attrs.uid);
				const url = assetUrl?.(node.attrs.uid) ?? null;

				if (url) {
					dom.src = url;
				} else {
					dom.alt = `[${node.attrs.uid}]`;
					dom.classList.add('cms-richtext-image-missing');
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

		getDoc() {
			return pmToDoc(view.state.doc);
		},

		getHTML() {
			return serializeContent(view.state);
		},

		setContent(html) {
			const newDoc = parseContent(html);
			const tr = view.state.tr.replaceWith(0, view.state.doc.content.size, newDoc.content);
			view.dispatch(tr);
		},

		destroy() {
			view.destroy();
		},
	};
}

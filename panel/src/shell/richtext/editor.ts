import { EditorState, type Command, type Plugin } from 'prosemirror-state';
import { EditorView } from 'prosemirror-view';
import { history } from 'prosemirror-history';
import { baseKeymap } from 'prosemirror-commands';
import { keymap } from 'prosemirror-keymap';
import { dropCursor } from 'prosemirror-dropcursor';
import { gapCursor } from 'prosemirror-gapcursor';
import { schema, parser, serializer } from './schema';
import { buildKeymap, buildInputRules } from './keymap';
import { bubbleMenu } from './bubble-menu';
import { docToPm, pmToDoc, type RichtextDoc } from './format';

/**
 * The internal editor driver seam: components talk documents in the
 * cosray richtext format plus the commands API — everything
 * ProseMirror-specific stays behind this module (and schema/format/
 * commands). A future editor swap replaces the driver, not the
 * callers.
 */
export interface CmsEditor {
	view: EditorView;
	run(command: Command): void;
	getDoc(): RichtextDoc;
	getHTML(): string;
	setContent(html: string): void;
	destroy(): void;
}

export interface EditorOptions {
	element: HTMLElement;
	content: RichtextDoc | null;
	onUpdate: (doc: RichtextDoc) => void;
	onStateChange: (state: EditorState) => void;
	mode: 'default' | 'inline';
	bubbleElement?: HTMLElement;
	/** Resolve an asset uid to a display URL for inline images. */
	assetUrl?: (uid: string) => string | null;
}

function parseContent(html: string) {
	const container = document.createElement('div');
	container.innerHTML = html;
	return parser.parse(container);
}

function serializeContent(state: EditorState): string {
	const fragment = serializer.serializeFragment(state.doc.content);
	const container = document.createElement('div');
	container.appendChild(fragment);
	return container.innerHTML;
}

export default function createEditor(options: EditorOptions): CmsEditor {
	const { element, content, onUpdate, onStateChange, mode, bubbleElement, assetUrl } = options;

	const plugins: Plugin[] = [
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

	const state = EditorState.create({
		doc: docToPm(content),
		schema,
		plugins,
	});

	const view = new EditorView(element, {
		state,
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

		run(command: Command) {
			view.focus();
			command(view.state, view.dispatch, view);
		},

		getDoc(): RichtextDoc {
			return pmToDoc(view.state.doc);
		},

		getHTML(): string {
			return serializeContent(view.state);
		},

		setContent(html: string) {
			const newDoc = parseContent(html);
			const tr = view.state.tr.replaceWith(0, view.state.doc.content.size, newDoc.content);
			view.dispatch(tr);
		},

		destroy() {
			view.destroy();
		},
	};
}

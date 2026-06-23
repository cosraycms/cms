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

export interface CmsEditor {
	view: EditorView;
	run(command: Command): void;
	getHTML(): string;
	setContent(html: string): void;
	destroy(): void;
}

export interface EditorOptions {
	element: HTMLElement;
	content: string;
	onUpdate: (html: string) => void;
	onStateChange: (state: EditorState) => void;
	mode: 'default' | 'inline';
	bubbleElement?: HTMLElement;
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
	const { element, content, onUpdate, onStateChange, mode, bubbleElement } = options;

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

	const doc = parseContent(content);

	const state = EditorState.create({
		doc,
		schema,
		plugins,
	});

	const view = new EditorView(element, {
		state,
		dispatchTransaction(tr) {
			const newState = view.state.apply(tr);
			view.updateState(newState);
			onStateChange(newState);
			if (tr.docChanged) {
				onUpdate(serializeContent(newState));
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

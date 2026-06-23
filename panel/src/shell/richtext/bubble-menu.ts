import { Plugin, PluginKey } from 'prosemirror-state';
import type { EditorView } from 'prosemirror-view';

const bubbleMenuKey = new PluginKey('bubbleMenu');

export function bubbleMenu(element: HTMLElement): Plugin {
	function update(view: EditorView) {
		const { state } = view;
		const { selection } = state;
		const { empty, from, to } = selection;

		if (empty || !view.hasFocus()) {
			element.style.display = 'none';
			return;
		}

		const start = view.coordsAtPos(from);
		const end = view.coordsAtPos(to);

		const editorRect = view.dom.parentElement?.getBoundingClientRect();
		if (!editorRect) {
			element.style.display = 'none';
			return;
		}

		element.style.display = '';

		const menuWidth = element.offsetWidth;
		const menuHeight = element.offsetHeight;

		const centerX = (start.left + end.left) / 2;
		let left = centerX - menuWidth / 2 - editorRect.left;
		const top = start.top - menuHeight - 8 - editorRect.top;

		left = Math.max(0, Math.min(left, editorRect.width - menuWidth));

		element.style.position = 'absolute';
		element.style.left = `${left}px`;
		element.style.top = `${top}px`;
		element.style.zIndex = '20';
	}

	return new Plugin({
		key: bubbleMenuKey,
		view() {
			element.style.display = 'none';
			return {
				update,
				destroy() {
					element.style.display = 'none';
				},
			};
		},
	});
}

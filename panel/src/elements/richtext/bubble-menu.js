/** @import { EditorView } from 'prosemirror-view' */

import { Plugin, PluginKey } from 'prosemirror-state';

const bubbleMenuKey = new PluginKey('bubbleMenu');

/**
 * @param {HTMLElement} element
 * @returns {Plugin}
 */
export function bubbleMenu(element) {
	/** @param {EditorView} view */
	function update(view) {
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

		// Offsets are relative to the positioned ancestor the bubble hangs
		// from, which is not the editor box; measure it once displayed. The
		// stylesheet positions the bubble absolutely, so showing it never
		// moves the text it is measured against.
		const originRect = element.offsetParent?.getBoundingClientRect() ?? editorRect;
		const menuWidth = element.offsetWidth;
		const menuHeight = element.offsetHeight;

		const centerX = (start.left + end.left) / 2;
		const minLeft = editorRect.left - originRect.left;
		let left = centerX - menuWidth / 2 - originRect.left;
		const top = start.top - menuHeight - 8 - originRect.top;

		left = Math.max(minLeft, Math.min(left, minLeft + editorRect.width - menuWidth));

		element.style.left = `${left}px`;
		element.style.top = `${top}px`;
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

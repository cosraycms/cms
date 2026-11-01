// The small richtext of a caption, read without an editor: a document with one
// paragraph of marked text, or a plain string from before captions had marks.

/** @import { RichtextDoc, RichtextNode } from '../types/data' */

const TAGS = { bold: 'strong', italic: 'em', strike: 's' };

/**
 * @param {RichtextDoc | string | null | undefined} value
 * @returns {RichtextNode[]}
 */
function runs(value) {
	if (!value || typeof value === 'string') return [];

	return value.content?.flatMap((block) => block.content ?? []) ?? [];
}

/**
 * @param {RichtextDoc | string | null | undefined} value
 * @returns {string}
 */
export function docText(value) {
	if (typeof value === 'string') return value.trim();

	return runs(value)
		.map((node) => node.text ?? '')
		.join('')
		.trim();
}

/**
 * The caption's text with its marks, ready to append.
 *
 * @param {RichtextDoc | string | null | undefined} value
 * @returns {(Node | string)[]}
 */
export function docNodes(value) {
	if (typeof value === 'string') return [value];

	return runs(value).map((node) => {
		/** @type {Node | string} */
		let shown = node.text ?? '';

		for (const mark of node.marks ?? []) {
			const tag = TAGS[/** @type {keyof typeof TAGS} */ (mark.type)];

			if (tag) {
				const element = document.createElement(tag);
				element.append(shown);
				shown = element;
			}
		}

		return shown;
	});
}

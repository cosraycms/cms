/**
 * Whether a key belongs to an input method's composition, such as the
 * Enter that confirms Japanese text. Safari reports isComposing false for
 * that last key, but keyCode 229 for every key the input method takes
 * (WebKit bug 165004).
 *
 * @param {KeyboardEvent} event
 * @returns {boolean}
 */
export function composing(event) {
	return event.isComposing || event.keyCode === 229;
}

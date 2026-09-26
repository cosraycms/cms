/**
 * Selection bookkeeping for the gallery drawer: the selected index has
 * to keep pointing at the same image while tiles are removed or dragged
 * into a new order around it.
 */

/**
 * @param {number | null} selected
 * @param {number} removed
 * @param {number} length The item count after the removal.
 * @returns {number | null}
 */
export function afterRemove(selected, removed, length) {
	if (selected === null || length === 0) {
		return null;
	}

	if (removed < selected) {
		return selected - 1;
	}

	return Math.min(selected, length - 1);
}

/**
 * @param {number | null} selected
 * @param {number} from
 * @param {number} to
 * @returns {number | null}
 */
export function afterMove(selected, from, to) {
	if (selected === null || from === to) {
		return selected;
	}

	if (selected === from) {
		return to;
	}

	if (from < selected && to >= selected) {
		return selected - 1;
	}

	if (from > selected && to <= selected) {
		return selected + 1;
	}

	return selected;
}

/** The tile aspect ratios a gallery may choose; `auto` keeps each image's own. */
export const RATIOS = /** @type {const} */ (['auto', '1/1', '4/3', '3/2', '16/9', '3/4', '2/3']);

/** @typedef {(typeof RATIOS)[number]} Ratio */

/**
 * @param {unknown} value
 * @returns {Ratio}
 */
export function readRatio(value) {
	return RATIOS.find((ratio) => ratio === value) ?? 'auto';
}

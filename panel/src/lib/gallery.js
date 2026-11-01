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

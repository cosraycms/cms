// Keeps a long gallery to two full rows until the editor unfolds it. The tiles
// always wrap to the row width, so the cut is measured instead of fixed.

import { __ } from '../../lib/locale.js';
import { button, svg } from './views.js';

const ROWS = 2;
const DURATION = 250;

/**
 * The columns the grid resolved to; none while it is not laid out, as in a
 * hidden tab.
 *
 * @param {HTMLElement} tiles
 * @returns {number}
 */
function columnsOf(tiles) {
	return tiles.clientWidth === 0
		? 0
		: getComputedStyle(tiles).gridTemplateColumns.split(' ').length;
}

/**
 * @typedef {object} FoldOptions
 * @property {HTMLElement} viewport Clips the tiles.
 * @property {HTMLElement} tiles
 * @property {boolean} expanded
 * @property {(expanded: boolean) => void} changed Keeps the state across the owner's renders.
 * @property {(tiles: HTMLElement) => number} [columns]
 */

/**
 * @param {FoldOptions} options
 * @returns {{ element: HTMLButtonElement, refresh: () => void, destroy: () => void }}
 */
export function galleryFold({ viewport, tiles, expanded, changed, columns = columnsOf }) {
	/** @type {Animation | null} */
	let motion = null;

	const toggle = button('cms-gallery-fold', '', () => {
		expanded = !expanded;
		changed(expanded);
		const from = viewport.getBoundingClientRect().height;
		apply();
		slide(from);
	});
	toggle.hidden = true;

	/** The tiles past the second row; none while the grid is not laid out. */
	function overflow() {
		const shown = columns(tiles) * ROWS;

		return shown === 0 ? 0 : Math.max(tiles.children.length - shown, 0);
	}

	/** Where the third row would begin, measured from the viewport's top. */
	function cut() {
		const last = tiles.children[tiles.children.length - overflow() - 1];
		const { rowGap } = getComputedStyle(tiles);

		return (
			last.getBoundingClientRect().bottom -
			viewport.getBoundingClientRect().top +
			(Number.parseFloat(rowGap) || 0)
		);
	}

	/**
	 * @param {number} from
	 */
	function slide(from) {
		motion?.cancel();
		const to = viewport.getBoundingClientRect().height;

		if (
			from === to ||
			!viewport.animate ||
			matchMedia('(prefers-reduced-motion: reduce)').matches
		) {
			settle();

			return;
		}

		viewport.classList.add('is-clipped');
		const current = viewport.animate([{ height: `${from}px` }, { height: `${to}px` }], {
			duration: DURATION,
			easing: 'ease',
		});
		motion = current;
		current.finished.then(settle, () => {});
	}

	function settle() {
		motion = null;
		viewport.classList.toggle('is-clipped', !expanded && overflow() > 0);
	}

	function apply() {
		const hidden = overflow();
		const folded = !expanded && hidden > 0;
		toggle.hidden = hidden === 0;

		if (hidden > 0) {
			toggle.replaceChildren(
				expanded ? __('image:show-fewer') : __('image:show-all', { count: tiles.children.length }),
				svg(expanded ? 'chevron-up' : 'chevron-down'),
			);
			toggle.setAttribute('aria-expanded', String(expanded));
		}

		const first = tiles.children.length - hidden;

		for (const [index, tile] of Array.from(tiles.children).entries()) {
			/** @type {HTMLElement} */ (tile).inert = folded && index >= first;
		}

		viewport.style.height = folded ? `${cut()}px` : '';

		if (!motion) settle();
	}

	const observer = new ResizeObserver(apply);
	observer.observe(tiles);

	return {
		element: toggle,
		refresh: apply,
		destroy() {
			observer.disconnect();
			motion?.cancel();
		},
	};
}

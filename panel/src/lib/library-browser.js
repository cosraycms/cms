// Embeds the asset library in a control's dialog. The server renders the
// picker (search, tiles, further pages) and htmx drives it; this loads it
// into place and reports the asset of a picked tile.

import { panelBase } from './runtime.js';

/**
 * An asset as the picker hands it over.
 *
 * @typedef {import('../types/data').AssetInfo & { uid: string, thumbUrl: string }} LibraryItem
 */

/**
 * @typedef {object} BrowseOptions
 * @property {'image' | 'video' | 'file' | null} [kind] Images or videos narrow the listing; a file
 *     context accepts every kind, so it browses the whole pool.
 * @property {string | null} [selected] The uid of the current pick, marked in the grid.
 * @property {(item: LibraryItem) => void} pick
 */

/**
 * @param {HTMLElement} container
 * @param {BrowseOptions} options
 * @returns {() => void} Stops reporting picks.
 */
export function browseLibrary(container, { kind = null, selected = null, pick }) {
	const params = new URLSearchParams();

	if (kind === 'image' || kind === 'video') {
		params.set('kind', kind);
	}

	if (selected) {
		params.set('file', selected);
	}

	const query = params.toString();

	/** @param {MouseEvent} event */
	function click(event) {
		const tile = event.target instanceof Element ? event.target.closest('[data-pick]') : null;

		if (!(tile instanceof HTMLElement) || !container.contains(tile)) {
			return;
		}

		for (const other of container.querySelectorAll('[data-pick]')) {
			other.classList.toggle('active', other === tile);
		}

		pick(JSON.parse(tile.dataset.pick ?? '{}'));
	}

	container.addEventListener('click', click);
	void htmx.ajax('GET', `${panelBase()}media/picker${query === '' ? '' : `?${query}`}`, {
		target: container,
		swap: 'innerHTML',
	});

	return () => container.removeEventListener('click', click);
}

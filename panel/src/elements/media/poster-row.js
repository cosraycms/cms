// The picture a video shows until it plays: which one, and the library to change it.

/** @import { AssetInfo } from '../../types/data' */
/** @import { LibraryItem } from '../../lib/library-browser.js' */

import { __ } from '../../lib/locale.js';
import { button, create } from './views.js';

/**
 * @param {object} options
 * @param {string | undefined} options.poster The picture's uid.
 * @param {(uid: string) => AssetInfo | undefined} options.asset
 * @param {(item: LibraryItem) => void} options.picked Makes an asset from the library known to `asset`.
 * @param {(pick: (item: LibraryItem) => void) => void} options.browse Opens the library.
 * @param {(uid: string | undefined) => void} options.change
 * @returns {HTMLElement}
 */
export function posterRow({ poster, asset, picked, browse, change }) {
	const element = create('div', 'cms-media-meta cms-video-poster');
	const entry = create('div', 'entry');
	const target = create('div', 'target');
	entry.append(create('span', 'caption', __('video:poster')), target);
	element.append(entry);
	let current = poster;

	/** @param {string | undefined} next */
	const set = (next) => {
		current = next;
		change(next);
		show();
		target.querySelector('button')?.focus();
	};

	const choose = () =>
		browse((item) => {
			picked(item);
			set(item.uid);
		});

	function show() {
		const info = current ? asset(current) : undefined;

		if (!current) {
			target.replaceChildren(button('cms-button secondary small', __('video:poster-add'), choose));

			return;
		}

		const parts = [];

		if (info?.thumbUrl ?? info?.url) {
			const image = document.createElement('img');
			image.src = info.thumbUrl ?? info.url ?? '';
			image.alt = '';
			parts.push(image);
		}

		target.replaceChildren(
			...parts,
			create('span', 'name', info?.filename ?? current),
			button('cms-button secondary small', __('media:replace'), choose),
			button('cms-button secondary small', __('common:remove'), () => set(undefined)),
		);
	}

	show();

	return element;
}

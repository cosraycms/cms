// An image's link for this use: where it points, and the link dialog to change
// it. The target is one of the richtext link's: an address, a page or a file.

/** @import { AssetInfo, ImageLink } from '../../types/data' */
/** @import { LibraryItem } from '../../lib/library-browser.js' */

import { __ } from '../../lib/locale.js';
import { nodeTitle, openLinkDialog } from '../richtext/dialogs.js';
import { button, create } from './views.js';

/**
 * Where a link points, in words; a page's title arrives later.
 *
 * @param {ImageLink} link
 * @param {(uid: string) => AssetInfo | undefined} asset
 * @returns {HTMLElement}
 */
export function linkText(link, asset) {
	const text = create('span', 'cms-image-link-text');

	/** @param {string} value */
	const show = (value) => {
		text.textContent = value;
		text.title = value;
	};

	if (link.node) {
		show(__('node:page'));
		void nodeTitle(link.node).then((title) => {
			if (title) show(title);
		});
	} else if (link.asset) {
		show(asset(link.asset)?.filename ?? link.asset);
	} else {
		show(link.href ?? '');
	}

	return text;
}

/**
 * @param {object} options
 * @param {HTMLElement} options.owner
 * @param {ImageLink | undefined} options.link
 * @param {(uid: string) => AssetInfo | undefined} options.asset
 * @param {(item: LibraryItem) => void} options.picked Makes an asset from the library known to `asset`.
 * @param {boolean} options.readonly
 * @param {(link: ImageLink | undefined) => void} options.change
 * @returns {{ element: HTMLElement, describe: (described: boolean) => void }}
 */
export function linkRow({ owner, link, asset, picked, readonly, change }) {
	const element = create('div', 'cms-media-meta cms-image-link');
	const entry = create('div', 'entry');
	const caption = create('span', 'caption', `${__('image:link')} `);
	caption.append(create('span', 'remark', __('field:optional')));
	const target = create('div', 'target');
	const warning = create('p', 'warning', __('image:link-no-alt'));
	entry.append(caption, target);
	element.append(entry);
	let current = link;
	let described = true;

	const warn = () => {
		if (current && !described) entry.append(warning);
		else warning.remove();
	};

	const open = () =>
		openLinkDialog({
			owner,
			href: current?.href ?? '',
			node: current?.node ?? '',
			asset: current?.asset ?? '',
			blank: current?.target === '_blank',
			add: (chosen, blank, item) => {
				if (item) picked(item);

				set({ ...chosen, ...(blank ? { target: '_blank' } : {}) });
			},
		});

	/** @param {ImageLink | undefined} next */
	const set = (next) => {
		current = next;
		change(next);
		show();
		warn();
		// The button that was used may be gone.
		target.querySelector('button')?.focus();
	};

	function show() {
		if (!current) {
			target.replaceChildren(
				...(readonly ? [] : [button('cms-button secondary small', __('link:add'), open)]),
			);

			return;
		}

		target.replaceChildren(linkText(current, asset));

		if (!readonly) {
			target.append(
				button('cms-button secondary small', __('common:edit'), open, 'pencil'),
				button('cms-button secondary small', __('common:remove'), () => set(undefined), 'x-lg'),
			);
		}
	}

	show();
	element.hidden = readonly && !current;

	return {
		element,
		describe(next) {
			described = next;
			warn();
		},
	};
}

// A stored link: where it points, and the link dialog to change it. The
// target is one of the richtext link's: an address, a page or a file.

/** @import { AssetInfo, StoredLink } from '../../types/data' */
/** @import { LibraryItem } from '../../lib/library-browser.js' */

import { __ } from '../../lib/locale.js';
import { button, create } from '../media/views.js';
import { nodeTitle, openLinkDialog } from '../richtext/dialogs.js';

/**
 * Where a link points, in words; a page's title arrives later.
 *
 * @param {StoredLink} link
 * @param {(uid: string) => AssetInfo | undefined} asset
 * @returns {HTMLElement}
 */
export function linkText(link, asset) {
	const text = create('span', 'cms-link-text');

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
 * @param {StoredLink | undefined} options.link
 * @param {(uid: string) => AssetInfo | undefined} options.asset
 * @param {(item: LibraryItem) => void} options.picked Makes an asset from the library known to `asset`.
 * @param {boolean} options.readonly
 * @param {(link: StoredLink | undefined) => void} options.change
 * @param {string} [options.caption] A heading of its own, where the row is not a field's whole control.
 * @param {boolean} [options.required] Marks the caption as required rather than optional.
 * @param {string} [options.warning] Shown while `describe(false)` says the link lacks a description.
 * @returns {{ element: HTMLElement, describe: (described: boolean) => void }}
 */
export function linkRow({
	owner,
	link,
	asset,
	picked,
	readonly,
	change,
	caption,
	required,
	warning,
}) {
	const element = create('div', 'cms-link-row');
	const entry = create('div', 'entry');
	const target = create('div', 'cms-link-row-target');
	const notice = create('p', 'cms-link-row-warning', warning ?? '');

	if (caption !== undefined) {
		const heading = create('span', 'caption', `${caption} `);
		heading.append(
			required
				? create('span', 'requirement', __('field:required'))
				: create('span', 'remark', __('field:optional')),
		);
		entry.append(heading);
	}

	entry.append(target);
	element.append(entry);
	let current = link;
	let described = true;

	const warn = () => {
		if (warning && current && !described) entry.append(notice);
		else notice.remove();
	};

	const open = () =>
		openLinkDialog({
			owner,
			href: current?.href ?? '',
			node: current?.node ?? '',
			asset: current?.asset ?? '',
			blank: current?.target === '_blank',
			rel: current?.rel ?? '',
			add: (chosen, blank, item) => {
				if (item) picked(item);

				set({ ...chosen, ...(blank ? { target: '_blank' } : {}) });
			},
		});

	/** @param {StoredLink | undefined} next */
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
				...(readonly
					? []
					: [button('cms-button cms-button-secondary cms-button-small', __('link:add'), open)]),
			);

			return;
		}

		target.replaceChildren(linkText(current, asset));

		if (!readonly) {
			target.append(
				button(
					'cms-button cms-button-secondary cms-button-small',
					__('common:edit'),
					open,
					'pencil',
				),
				button(
					'cms-button cms-button-secondary cms-button-small',
					__('common:remove'),
					() => set(undefined),
					'x-lg',
				),
			);
		}
	}

	show();
	element.hidden = readonly && !current && caption !== undefined;

	return {
		element,
		describe(next) {
			described = next;
			warn();
		},
	};
}

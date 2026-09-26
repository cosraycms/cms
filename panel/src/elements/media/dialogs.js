// The dialogs of the media controls, opened through the window.Cosray bridge.

/** @import { FileItem, Meta, UploadType } from '../../types/data' */
/** @import { ControlLocales } from '../../lib/control.js' */
/** @import { LibraryItem } from '../../lib/library-browser.js' */

import { cosray } from '../../lib/bridge.js';
import { browseLibrary } from '../../lib/library-browser.js';
import { __ } from '../../lib/locale.js';
import { metaFields } from './meta-fields.js';
import { contentLocales } from './views.js';

/**
 * @param {string} label
 * @param {'primary' | 'secondary'} variant
 * @param {() => void} onclick
 * @returns {HTMLButtonElement}
 */
function button(label, variant, onclick) {
	const element = document.createElement('button');
	element.type = 'button';
	element.className = `cms-button ${variant}`;
	element.textContent = label;
	element.addEventListener('click', onclick);

	return element;
}

/**
 * @param {HTMLElement} host
 * @param {string} title
 * @param {Node[]} body
 * @param {HTMLElement[]} [footer]
 */
function frame(host, title, body, footer) {
	const header = document.createElement('header');
	header.className = 'modal-header';
	const heading = document.createElement('h2');
	heading.className = 'modal-title';
	heading.dataset.dialogTitle = '';
	heading.textContent = title;
	header.append(heading);
	const main = document.createElement('div');
	main.className = 'modal-body';
	main.append(...body);
	host.append(header, main);

	if (footer) {
		const foot = document.createElement('footer');
		foot.className = 'modal-footer';
		foot.append(...footer);
		host.append(foot);
	}
}

/**
 * @param {HTMLElement | undefined} owner
 * @param {string} body
 */
export function alertDialog(owner, body) {
	const handle = cosray().modal.open(
		(host) => {
			const message = document.createElement('p');
			message.className = 'cms-dialog-message is-error';
			message.textContent = body;
			const ok = button(__('common:ok'), 'secondary', () => handle.close());
			ok.dataset.dialogFocus = '';
			frame(host, __('common:error'), [message], [ok]);
		},
		{ owner, size: 'compact' },
	);
}

/**
 * @param {HTMLElement | undefined} owner
 * @param {string} url
 */
export function previewDialog(owner, url) {
	cosray().modal.open(
		(host) => {
			const box = document.createElement('div');
			box.className = 'cms-image-preview';
			const image = document.createElement('img');
			image.src = url;
			image.alt = __('common:preview');
			box.append(image);
			frame(host, __('common:preview'), [box]);
		},
		{ owner, size: 'wide' },
	);
}

/**
 * The library to pick one asset from; a pick closes the dialog.
 *
 * @param {HTMLElement | undefined} owner
 * @param {UploadType} kind
 * @param {(item: LibraryItem) => void} pick
 */
export function libraryDialog(owner, kind, pick) {
	const handle = cosray().modal.open(
		(host) => {
			const library = document.createElement('div');
			frame(
				host,
				__('media:choose-from-library'),
				[library],
				[button(__('common:cancel'), 'secondary', () => handle.close())],
			);

			return browseLibrary(library, {
				kind,
				pick(item) {
					handle.close();
					pick(item);
				},
			});
		},
		{ owner },
	);
}

/**
 * @typedef {object} EditOptions
 * @property {HTMLElement | undefined} owner
 * @property {FileItem} item
 * @property {'file' | 'video'} kind A video edits its caption, a file its title.
 * @property {boolean} translate
 * @property {string} contentLocale
 * @property {ControlLocales | undefined} locales
 * @property {Meta | undefined} catalog
 * @property {(item: FileItem) => void} apply Receives the draft; cancelling discards it.
 */

/**
 * Edits an item's texts on a draft. The returned function hands the dialog
 * a language chosen meanwhile, as long as it is open.
 *
 * @param {EditOptions} options
 * @returns {{ setLocale: (locale: string) => void, closed: Promise<void> }}
 */
export function editDialog({
	owner,
	item,
	kind,
	translate,
	contentLocale,
	locales,
	catalog,
	apply,
}) {
	/** @type {FileItem} */
	let draft = structuredClone(item);
	/** @type {((locale: string) => void)[]} */
	const followers = [];
	const closed = Promise.withResolvers();

	const handle = cosray().modal.open(
		(host) => {
			const fields = document.createElement('div');
			fields.className = 'cms-modal-edit-image-fields';

			if (translate && locales && locales.all.length > 1) {
				const mirror = contentLocales(locales.all, contentLocale);
				const wrap = document.createElement('div');
				wrap.className = 'cms-modal-edit-image-locales';
				wrap.append(mirror.element);
				fields.append(wrap);
				followers.push(mirror.setLocale);
			}

			const form = metaFields({
				item: draft,
				kind,
				translate,
				contentLocale,
				locales,
				catalog,
				update: (next) => (draft = next),
			});
			fields.append(form.element);
			followers.push(form.setLocale);
			frame(
				host,
				kind === 'video' ? __('image:caption') : __('media:file-details'),
				[fields],
				[
					button(__('common:cancel'), 'secondary', () => handle.close()),
					button(__('common:apply'), 'primary', () => {
						handle.close();
						apply(draft);
					}),
				],
			);
			/** @type {HTMLElement | null} */ (
				form.element.querySelector('input, textarea')
			)?.setAttribute('data-dialog-focus', '');

			return () => {
				followers.length = 0;
				closed.resolve(undefined);
			};
		},
		{ owner },
	);

	return {
		setLocale(locale) {
			for (const follow of followers) follow(locale);
		},
		closed: closed.promise,
	};
}

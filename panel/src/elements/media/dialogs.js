// The dialogs of the media controls, opened through the window.Cosray bridge.

/** @import { AssetInfo, FileItem, ItemMeta, UploadType } from '../../types/data' */
/** @import { ControlLocales } from '../../lib/control.js' */
/** @import { LibraryItem } from '../../lib/library-browser.js' */

import { cosray } from '../../lib/bridge.js';
import { patchItemMeta } from '../../lib/content.js';
import { browseLibrary } from '../../lib/library-browser.js';
import { __ } from '../../lib/locale.js';
import { button, frame } from '../../lib/modal.js';
import { metaFields } from './meta-fields.js';
import { posterRow } from './poster-row.js';
import { contentLocales } from './views.js';

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
 * @property {('caption' | 'title')[]} texts
 * @property {import('../../types/data').RequiredItem[]} required
 * @property {boolean} translate
 * @property {string} contentLocale
 * @property {ControlLocales | undefined} locales
 * @property {ItemMeta | undefined} catalog
 * @property {(uid: string) => AssetInfo | undefined} asset
 * @property {(item: LibraryItem) => void} picked Makes an asset from the library known to `asset`.
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
	texts,
	required,
	translate,
	contentLocale,
	locales,
	catalog,
	asset,
	picked,
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
			fields.className = 'cms-media-details-fields';

			if (translate && locales && locales.all.length > 1) {
				const mirror = contentLocales(locales.all, contentLocale);
				const wrap = document.createElement('div');
				wrap.className = 'cms-media-details-locales';
				wrap.append(mirror.element);
				fields.append(wrap);
				followers.push(mirror.setLocale);
			}

			const form = metaFields({
				item: draft,
				kind,
				texts,
				required,
				translate,
				contentLocale,
				locales,
				catalog,
				update: (texts) => (draft = patchItemMeta(draft, texts)),
			});
			fields.append(form.element);
			followers.push(form.setLocale);

			if (kind === 'video') {
				fields.append(
					posterRow({
						poster: typeof draft.meta?.poster === 'string' ? draft.meta.poster : undefined,
						asset,
						picked,
						browse: (pick) => libraryDialog(owner ?? host, 'image', pick),
						change: (poster) => (draft = patchItemMeta(draft, { poster })),
					}),
				);
			}

			const body = frame(
				host,
				kind === 'video' ? __('media:video-details') : __('media:file-details'),
				[fields],
				[
					button(__('common:cancel'), 'secondary', () => handle.close()),
					button(__('common:apply'), 'primary', () => {
						handle.close();
						apply(draft);
					}),
				],
			);
			body.classList.add('cms-media-details');
			/** @type {HTMLElement | null} */ (
				form.element.querySelector('input, textarea, [contenteditable]')
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

// The details of the images of a field: the picture with its facts next to
// its per-use texts. Everything stays a draft until the dialog is applied,
// also across the images a gallery steps through.

/** @import { AssetInfo, FileItem } from '../../types/data' */
/** @import { ControlLocales } from '../../lib/control.js' */
/** @import { LibraryItem } from '../../lib/library-browser.js' */

import { cosray } from '../../lib/bridge.js';
import { extension, humanSize } from '../../lib/library.js';
import { __ } from '../../lib/locale.js';
import { libraryDialog } from './dialogs.js';
import { metaFields } from './meta-fields.js';
import { button, contentLocales, create, replaceMenu, svg, rendition } from './views.js';

/**
 * @typedef {object} ImageDialogOptions
 * @property {HTMLElement} owner
 * @property {FileItem[]} items The images to step through.
 * @property {number} index The image to open on.
 * @property {(uid: string) => AssetInfo | undefined} asset
 * @property {(item: LibraryItem) => void} picked Makes an asset from the library known to `asset`.
 * @property {(file: File) => Promise<string | null>} upload Resolves to the uid of the new asset,
 *     known to `asset`, or to null once the editor was told what went wrong.
 * @property {boolean} translate
 * @property {string} contentLocale
 * @property {ControlLocales | undefined} locales
 * @property {boolean} readonly
 * @property {(drafts: Map<number, FileItem>) => void} apply Receives the changed images by index.
 */

/**
 * The picture's address, to read and to copy. Without a secure context, as on
 * a plain http development host, there is no clipboard API to write to.
 *
 * @param {string} url
 * @returns {HTMLElement}
 */
function address(url) {
	const row = create('div', 'address');
	const input = document.createElement('input');
	input.className = 'cms-input';
	input.readOnly = true;
	input.value = url;
	input.setAttribute('aria-label', __('media:url'));
	const copy = button(
		'copy',
		__('common:copy'),
		() => {
			void (async () => {
				try {
					await navigator.clipboard.writeText(input.value);
				} catch {
					input.select();
					document.execCommand('copy');
				}

				copy.replaceChildren(svg('check-lg'));
				copy.title = __('common:copied');
				setTimeout(() => {
					copy.replaceChildren(svg('copy'));
					copy.title = __('common:copy');
				}, 1500);
			})();
		},
		'copy',
	);
	row.append(input, copy);

	return row;
}

/**
 * @param {string} label
 * @param {Node | string} value
 * @param {string} [className]
 * @returns {HTMLElement}
 */
function fact(label, value, className) {
	const row = create('div', className);
	const term = create('dt', undefined, label);
	const detail = create('dd');
	detail.append(value);
	row.append(term, detail);

	return row;
}

/**
 * @param {AssetInfo | undefined} info
 * @returns {HTMLElement[]}
 */
function facts(info) {
	if (!info) {
		return [];
	}

	const format = extension(info.filename);

	return [
		...(typeof info.bytes === 'number' ? [fact(__('media:size'), humanSize(info.bytes))] : []),
		...(info.width && info.height
			? [fact(__('media:dimensions'), `${info.width} × ${info.height} px`)]
			: []),
		...(format ? [fact(__('media:format'), format)] : []),
		...(info.url
			? [fact(__('media:url'), address(new URL(info.url, location.href).href), 'wide')]
			: []),
	];
}

/**
 * @param {ImageDialogOptions} options
 * @returns {{ setLocale: (locale: string) => void, closed: Promise<void> }}
 */
export function imageDialog({
	owner,
	items,
	index,
	asset,
	picked,
	upload,
	translate,
	contentLocale,
	locales,
	readonly,
	apply,
}) {
	let at = index;
	let locale = contentLocale;
	let open = true;
	/** @type {Map<number, FileItem>} */
	const drafts = new Map();
	/** @type {((locale: string) => void)[]} */
	const followers = [];
	const closed = Promise.withResolvers();
	/** @type {ReturnType<typeof metaFields> | null} */
	let form = null;

	const handle = cosray().modal.open(
		(host) => {
			const header = create('header', 'modal-header cms-image-head');
			const heading = create('div', 'heading');
			const title = create('h2', 'modal-title', __('image:details'));
			title.dataset.dialogTitle = '';
			const filename = create('p', 'filename');
			heading.append(title, filename);
			header.append(heading);
			const place = create('span', 'position');

			/** @param {number} delta */
			const step = (delta) => {
				at = (at + delta + items.length) % items.length;
				show();
			};

			if (items.length > 1) {
				const stepper = create('div', 'stepper');
				stepper.append(
					place,
					button('step prev', __('image:previous'), () => step(-1), 'chevron-left'),
					button('step next', __('image:next'), () => step(1), 'chevron-right'),
				);
				header.append(stepper);
			}

			const body = create('div', 'modal-body cms-image-details');
			const stage = create('div', 'stage');
			const preview = create('div', 'preview');
			stage.append(preview);
			const fields = create('div', 'fields');
			const list = create('dl', 'facts');
			body.append(stage, fields);

			if (translate && locales && locales.all.length > 1) {
				const mirror = contentLocales(locales.all, locale);
				fields.append(mirror.element);
				followers.push(mirror.setLocale);
			}

			function show() {
				const item = drafts.get(at) ?? items[at];
				const info = asset(item.uid ?? '');
				const source = info?.previewUrl ?? info?.url ?? '';
				const name = info?.filename ?? item.uid ?? '';
				filename.textContent = name;
				filename.title = name;
				place.textContent = `${at + 1} / ${items.length}`;

				if (source) {
					preview.replaceChildren(rendition(source, info?.url));
				} else {
					preview.replaceChildren(create('span', 'plate', extension(name)));
				}

				list.replaceChildren(...facts(info));
				form?.element.remove();
				const target = at;
				form = metaFields({
					item,
					kind: 'image',
					translate,
					contentLocale: locale,
					locales,
					catalog: info?.meta,
					update: (next) => drafts.set(target, next),
					readonly,
				});
				fields.append(form.element, list);

				if (!readonly) {
					form.element.querySelector('textarea')?.setAttribute('data-dialog-focus', '');
				}
			}

			const footer = create('footer', 'modal-footer');

			if (readonly) {
				const close = button('cms-button secondary', __('common:close'), () => handle.close());
				close.dataset.dialogFocus = '';
				footer.append(close);
			} else {
				/** @param {FileItem} item */
				const swap = (item, target = at) => {
					// The texts described the image that is gone.
					drafts.set(target, item);

					if (target === at) show();
				};
				const picker = document.createElement('input');
				picker.type = 'file';
				picker.hidden = true;
				picker.accept = cosray()
					.system()
					.allowedFiles.image.map((suffix) => `.${suffix}`)
					.join(',');
				const [trigger, menu] = replaceMenu(
					() => picker.click(),
					() =>
						libraryDialog(owner, 'image', (item) => {
							picked(item);
							swap({ uid: item.uid });
						}),
					false,
					'start',
				);
				trigger.classList.remove('small');
				const cancel = button('cms-button secondary', __('common:cancel'), () => handle.close());
				const confirm = button('cms-button primary', __('common:apply'), () => {
					handle.close();

					if (drafts.size > 0) apply(drafts);
				});
				picker.addEventListener('input', () => {
					const [file] = picker.files ?? [];
					picker.value = '';

					if (!file) return;

					const target = at;
					const label = trigger.firstChild;
					trigger.toggleAttribute('disabled', true);
					confirm.disabled = true;
					label?.replaceWith(__('upload:uploading'));
					void upload(file).then((uid) => {
						if (!open) return;

						trigger.firstChild?.replaceWith(__('media:replace'));
						trigger.removeAttribute('disabled');
						confirm.disabled = false;

						if (uid) swap({ uid }, target);
					});
				});
				footer.append(trigger, menu, picker, cancel, confirm);
			}

			host.append(header, body, footer);
			show();

			return () => {
				open = false;
				followers.length = 0;
				closed.resolve(undefined);
			};
		},
		{ owner, size: 'stage' },
	);

	return {
		setLocale(next) {
			locale = next;
			form?.setLocale(next);

			for (const follow of followers) follow(next);
		},
		closed: closed.promise,
	};
}

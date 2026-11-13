// The details of the images of a field: the picture with its facts next to
// its per-use texts. Everything stays a draft until the dialog is applied,
// also across the images a gallery steps through.

/** @import { AssetInfo, FileItem, ItemMeta, RequiredItem } from '../../types/data' */
/** @import { ControlLocales } from '../../lib/control.js' */
/** @import { LibraryItem } from '../../lib/library-browser.js' */

import { cosray } from '../../lib/bridge.js';
import { patchItemMeta } from '../../lib/content.js';
import { docText } from '../../lib/inline-doc.js';
import { extension, humanSize } from '../../lib/library.js';
import { __ } from '../../lib/locale.js';
import { libraryDialog } from './dialogs.js';
import { imageStage } from './image-stage.js';
import { linkRow } from '../link/row.js';
import { metaFields } from './meta-fields.js';
import { button, contentLocales, create, effectiveText, replaceMenu, svg } from './views.js';

/**
 * @typedef {object} ImageDialogOptions
 * @property {HTMLElement} owner
 * @property {FileItem[]} items The images to step through.
 * @property {number} index The image to open on.
 * @property {(uid: string) => AssetInfo | undefined} asset
 * @property {(item: LibraryItem) => void} picked Makes an asset from the library known to `asset`.
 * @property {(file: File) => Promise<string | null>} upload Resolves to the uid of the new asset,
 *     known to `asset`, or to null once the editor was told what went wrong.
 * @property {('alt' | 'caption' | 'attribution')[]} texts The texts the field offers.
 * @property {boolean} linking Whether the field offers a link per image.
 * @property {RequiredItem[]} required What the field requires of each image.
 * @property {boolean} cropping Whether the site can deliver a crop.
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
 * Whether a rendition can cut the image: not a vector, and not a GIF, whose
 * animation only survives as a copy of the original.
 *
 * @param {AssetInfo | undefined} info
 * @returns {boolean}
 */
function croppable(info) {
	return ['image/avif', 'image/jpeg', 'image/png', 'image/webp'].includes(info?.mime ?? '');
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
	texts,
	linking,
	required,
	cropping,
	translate,
	contentLocale,
	locales,
	readonly,
	apply,
}) {
	let at = index;
	/** @type {'focus' | 'crop'} */
	let tool = 'focus';
	let locale = contentLocale;
	let open = true;
	/** @type {Map<number, FileItem>} */
	const drafts = new Map();
	/** @type {((locale: string) => void)[]} */
	const followers = [];
	const closed = Promise.withResolvers();
	/** @type {ReturnType<typeof metaFields> | null} */
	let form = null;
	/** @type {(() => void) | null} */
	let describeLink = null;

	/**
	 * The picture and the form each report their own part of an image's draft.
	 *
	 * @param {number} target
	 * @param {ItemMeta} patch
	 */
	const edit = (target, patch) =>
		drafts.set(target, patchItemMeta(drafts.get(target) ?? items[target], patch));

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
			const fields = create('div', 'fields');
			const list = create('dl', 'facts');
			/** @type {ReturnType<typeof linkRow> | null} */
			let row = null;
			body.append(stage, fields);

			if (translate && locales && locales.all.length > 1) {
				const mirror = contentLocales(locales.all, locale);
				fields.append(mirror.element);
				followers.push(mirror.setLocale);
			}

			function show() {
				const target = at;
				const item = drafts.get(target) ?? items[target];
				const info = asset(item.uid ?? '');
				const source = info?.previewUrl ?? info?.url ?? '';
				const name = info?.filename ?? item.uid ?? '';
				filename.textContent = name;
				filename.title = name;
				place.textContent = `${at + 1} / ${items.length}`;

				if (source) {
					const shown = imageStage({
						item,
						info,
						source,
						readonly,
						cropping: cropping && !readonly && croppable(info),
						mode: tool,
						modeChanged: (next) => (tool = next),
						change: (patch) => edit(target, patch),
					});
					stage.replaceChildren(...shown.nodes);
				} else {
					const preview = create('div', 'preview');
					preview.append(create('span', 'plate', extension(name)));
					stage.replaceChildren(preview);
				}

				list.replaceChildren(...facts(info));
				form?.element.remove();
				row?.element.remove();
				form = metaFields({
					item,
					kind: 'image',
					texts,
					required,
					translate,
					contentLocale: locale,
					locales,
					catalog: info?.meta,
					update: (texts) => {
						edit(target, texts);
						describeLink?.();
					},
					readonly,
				});
				row = linking
					? linkRow({
							owner,
							link: item.meta?.link,
							asset,
							picked,
							caption: __('image:link'),
							required: required.includes('link'),
							warning: __('image:link-no-alt'),
							readonly,
							change: (link) => edit(target, { link }),
						})
					: null;
				row?.element.classList.add('cms-media-meta');
				describeLink = () =>
					row?.describe(
						Boolean(
							docText(
								effectiveText(
									'alt',
									drafts.get(target) ?? items[target],
									info,
									translate,
									locale,
									locales,
								),
							),
						),
					);
				describeLink();
				fields.append(form.element, ...(row ? [row.element] : []), list);

				if (!readonly) {
					form.element.querySelector('textarea')?.setAttribute('data-dialog-focus', '');
				}
			}

			const footer = create('footer', 'modal-footer');

			if (readonly) {
				const close = button('cms-button cms-button-secondary', __('common:close'), () =>
					handle.close(),
				);
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
				trigger.classList.remove('cms-button-small');
				const cancel = button('cms-button cms-button-secondary', __('common:cancel'), () =>
					handle.close(),
				);
				const confirm = button('cms-button cms-button-primary', __('common:apply'), () => {
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
			describeLink?.();

			for (const follow of followers) follow(next);
		},
		closed: closed.promise,
	};
}

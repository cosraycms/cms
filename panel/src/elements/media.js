// Defines <cosray-image>, <cosray-file> and <cosray-video>: the media
// controls. A frame with the library and upload actions holds the field's
// files as rows, a single image as a card and several as a gallery; in a
// block a filled field shows its image or video as content and puts the
// per-use texts into the settings slot. Values are `{uid, meta?}` lists:
// an asymmetric field keeps one per language, any other one under zxx.

/** @import { AssetInfo, AssetMap, FileItem, LocaleMap, Meta, UploadType } from '../types/data' */
/** @import { ControlLocales } from '../lib/control.js' */

import Sortable from 'sortablejs';
import { cosray } from '../lib/bridge.js';
import { ZXX, pruneItemMeta } from '../lib/content.js';
import { fallbackLabel, reportChange } from '../lib/control.js';
import { resolveFallback } from '../lib/fallback.js';
import { __ } from '../lib/locale.js';
import { alertDialog, editDialog, libraryDialog, previewDialog } from './media/dialogs.js';
import { galleryView } from './media/gallery-view.js';
import { metaFields } from './media/meta-fields.js';
import {
	button,
	contentLocales,
	create,
	effectiveCaption,
	fallbackMedia,
	figure,
	fileRow,
	imageCard,
	svg,
	retitle,
	videoPlayer,
} from './media/views.js';

/**
 * @typedef {object} MediaField
 * @property {string} [name]
 * @property {boolean} [immutable] The field cannot change: no bar, no drop zone, no per-item
 *     actions.
 * @property {boolean} [translate]
 * @property {'symmetric' | 'asymmetric'} [translateMode]
 * @property {{ min?: number, max?: number }} [limit]
 * @property {string} [presentation] `block` shows a filled field as content alone.
 */

/**
 * @param {FileItem[] | undefined} candidate
 * @returns {boolean}
 */
function hasItems(candidate) {
	return candidate?.some((item) => typeof item.uid === 'string' && item.uid !== '') ?? false;
}

/**
 * @param {Element} child
 * @returns {number}
 */
function position(child) {
	return child.parentElement
		? Array.prototype.indexOf.call(child.parentElement.children, child)
		: -1;
}

/**
 * @param {DragEvent} event
 * @returns {boolean}
 */
function carriesFiles(event) {
	return event.dataTransfer?.types.includes('Files') ?? false;
}

class MediaControl extends HTMLElement {
	/** @type {LocaleMap<FileItem[]> | null | undefined} */
	value = {};
	/** @type {Meta | undefined} */
	meta;
	/** @type {MediaField} */
	field;
	node = '';
	/** @type {ControlLocales | undefined} */
	locales;
	/** @type {AssetMap} */
	assets = {};
	/** @type {HTMLElement | undefined} */
	settings;

	/** @type {UploadType} */
	#type;
	#locale = ZXX;
	#rendered = false;
	/** @type {LocaleMap<FileItem[]>} */
	#map = {};
	#started = false;
	/** @type {Meta | undefined} */
	#fieldMeta;
	// Library picks and uploads register here so previews resolve before
	// the payload knows the asset.
	/** @type {AssetMap} */
	#picked = {};
	/** @type {Map<string, number>} */
	#pending = new Map();
	/** @type {number | null} */
	#selected = null;
	#depth = 0;
	/** @type {HTMLElement | null} */
	#frame = null;
	/** @type {HTMLInputElement | null} */
	#picker = null;
	/** @type {HTMLElement[]} What this control put into the settings slot. */
	#slotted = [];
	/** @type {(() => void)[]} */
	#teardown = [];
	/** @type {((locale: string) => void)[]} */
	#followers = [];
	/** @type {Set<(locale: string) => void>} */
	#dialogs = new Set();

	/** @param {UploadType} type */
	constructor(type) {
		super();
		this.#type = type;
		this.field = { name: type };
	}

	/** @type {string} */
	get locale() {
		return this.#locale;
	}

	// The content-language behavior switches the locale through the host:
	// an asymmetric field then shows another list, a translated one other
	// texts of the same list.
	set locale(locale) {
		const previous = this.#locale;
		this.#locale = locale;

		if (!this.#rendered || previous === locale) {
			return;
		}

		if (this.#identity(previous) !== this.#identity(locale)) {
			this.#selected = null;
			this.#render();
		} else if (this.#translate) {
			for (const follow of [...this.#followers, ...this.#dialogs]) follow(locale);
		}
	}

	connectedCallback() {
		if (this.#rendered) {
			return;
		}

		if (!this.#started) {
			this.#started = true;
			// A control the host renders without stored data assigns null.
			this.#map = structuredClone(this.value ?? {});
			// Only an image field keeps meta, its gallery settings; the others
			// leave the field's meta to its own controls.
			this.#fieldMeta =
				this.#type === 'image' && this.meta ? structuredClone(this.meta) : undefined;
		}

		this.#rendered = true;
		this.#render();
	}

	// Sorting blocks moves the element; only a removal that outlasts the
	// current task tears it down.
	disconnectedCallback() {
		queueMicrotask(() => {
			if (!this.isConnected && this.#rendered) {
				this.#rendered = false;
				this.#clear();
				this.replaceChildren();
			}
		});
	}

	/**
	 * The list a locale edits.
	 *
	 * @param {string} locale
	 * @returns {string}
	 */
	#identity(locale) {
		return this.field.translateMode === 'asymmetric' ? locale : ZXX;
	}

	// An asymmetric field's list is per language already; its texts are not.
	get #translate() {
		return this.field.translateMode === 'asymmetric' ? false : (this.field.translate ?? false);
	}

	get #max() {
		return this.field.limit?.max ?? -1;
	}

	/**
	 * @param {string} uid
	 * @returns {AssetInfo | undefined}
	 */
	#asset(uid) {
		return this.#picked[uid] ?? this.assets?.[uid];
	}

	// Meta travels only once the element has any, so a plain image field
	// keeps submitting without a meta member.
	#notify() {
		/** @type {{ value: LocaleMap<FileItem[]>, meta?: Meta }} */
		const detail = { value: this.#map };

		if (this.#fieldMeta !== undefined) {
			detail.meta = this.#fieldMeta;
		}

		reportChange(this, detail);
	}

	/**
	 * A fresh item carries only its uid: per-use meta stays absent until the
	 * editor fills it, so catalog defaults keep applying. A single field
	 * replaces what it holds.
	 *
	 * @param {string} identity
	 * @param {FileItem} item
	 */
	#add(identity, item) {
		this.#map[identity] = this.#max === 1 ? [item] : [...(this.#map[identity] ?? []), item];
	}

	#clear() {
		for (const stop of this.#teardown.splice(0)) stop();

		for (const node of this.#slotted.splice(0)) node.remove();

		this.#followers = [];
		this.#frame = null;
		this.#picker = null;
		this.#depth = 0;
	}

	/**
	 * Finds the focused part again after a render, by its place among the
	 * new parts, as long as it is still the same kind of control.
	 *
	 * @returns {(() => void) | null}
	 */
	#keepFocus() {
		const active = document.activeElement;
		const roots = [this, ...this.#slotted];
		const root = roots.find((candidate) => candidate !== active && candidate.contains(active));

		if (!(active instanceof HTMLElement) || !root) {
			return null;
		}

		/** @type {number[]} */
		const path = [];

		for (let node = active; node !== root && node.parentElement; node = node.parentElement) {
			path.unshift(position(node));
		}

		const which = roots.indexOf(root);
		const { tagName, className } = active;
		const range =
			active instanceof HTMLInputElement || active instanceof HTMLTextAreaElement
				? [active.selectionStart, active.selectionEnd]
				: null;

		return () => {
			/** @type {Element | undefined} */
			let node = [this, ...this.#slotted][which];

			for (const index of path) node = node?.children[index];

			if (
				!(node instanceof HTMLElement) ||
				node.tagName !== tagName ||
				node.className !== className
			) {
				return;
			}

			node.focus({ preventScroll: true });

			if (range && (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement)) {
				node.setSelectionRange(range[0], range[1]);
			}
		};
	}

	/** @param {HTMLElement} node */
	#slot(node) {
		if (this.settings) {
			this.settings.append(node);
			this.#slotted.push(node);

			return true;
		}

		return false;
	}

	#render() {
		const restore = this.#keepFocus();
		this.#clear();

		const type = this.#type;
		const field = this.field;
		const locale = this.#locale;
		const locales = this.locales;
		const all = locales?.all ?? [];
		const translate = this.#translate;
		const identity = this.#identity(locale);
		const items = this.#map[identity] ?? [];
		const max = this.#max;
		const multiple = max < 1 || max > 1;
		const open = max < 1 || items.length < max;
		const full = multiple && !open;
		const block = field.presentation === 'block' && (type === 'image' || type === 'video');
		const empty = items.length === 0;
		const readonly = field.immutable ?? false;
		const loading = (this.#pending.get(identity) ?? 0) > 0;
		/** @param {string} uid */
		const asset = (uid) => this.#asset(uid);
		const upload = () => this.#picker?.click();
		const library = () => this.#library(identity);
		/** @type {Node[]} */
		const nodes = [];

		if (field.translateMode === 'asymmetric' && !hasItems(items)) {
			const fallback = resolveFallback(this.#map, identity, all, hasItems);

			if (fallback) {
				nodes.push(
					fallbackMedia(fallback.value, type, fallbackLabel(fallback, all), asset, false, locale),
				);
			}
		}

		const frame = create('div', 'cms-media-field cms-dropzone');
		frame.classList.toggle('is-block', block);
		frame.classList.toggle('is-empty', empty);
		frame.classList.toggle('is-readonly', readonly);
		frame.setAttribute('role', 'group');
		this.#frame = frame;
		nodes.push(frame);

		// A filled single field replaces through its own menu instead.
		if (!readonly && (empty || (multiple && !block))) {
			frame.append(this.#bar(items.length, { multiple, empty, full, loading, library, upload }));
		}

		if (readonly && empty) {
			frame.append(
				create('div', 'none', multiple ? __('media:empty-many') : __('media:empty-one')),
			);
		}

		/** @param {FileItem[]} next */
		const store = (next) => {
			this.#map[identity] = next;
			this.#notify();
		};
		const removeAll = () => {
			store([]);
			this.#render();
		};
		/** @param {number} index */
		const removeAt = (index) => {
			items.splice(index, 1);
			store(items);
			this.#render();
		};
		/**
		 * @param {number} from
		 * @param {number} to
		 */
		const move = (from, to) => {
			const [moved] = items.splice(from, 1);
			items.splice(to, 0, moved);
			// The host only serializes into the form value when notified;
			// without this the reorder is lost on save.
			store(items);
		};

		// A block gallery mounts while empty too: its settings live in the slot.
		if (!empty || (block && multiple && type === 'image')) {
			const body = create('div', 'body');
			frame.append(body);

			if (type === 'image' && multiple) {
				const gallery = galleryView({
					items,
					asset,
					loading,
					translate,
					contentLocale: locale,
					locales,
					open,
					readonly,
					block,
					meta: this.#fieldMeta,
					selected: this.#selected,
					select: (selected) => (this.#selected = selected),
					update: (index, item) => {
						items[index] = item;
						store(items);
					},
					updateMeta: (meta) => {
						this.#fieldMeta = meta;
						this.#notify();
					},
					remove: removeAt,
					move,
					upload,
					library,
				});
				body.append(gallery.element);

				if (gallery.slot && !this.#slot(gallery.slot)) {
					body.append(gallery.slot);
				}

				this.#teardown.push(gallery.destroy);
				this.#followers.push(gallery.setLocale);
			} else if (type === 'image' || block) {
				this.#single(body, items[0], {
					block,
					loading,
					readonly,
					store,
					removeAll,
					upload,
					library,
				});
			} else {
				if (type === 'video') {
					body.append(videoPlayer(asset(items[0].uid ?? '')));
				}

				body.append(
					this.#rows(identity, items, {
						multiple,
						loading,
						readonly,
						removeAt,
						move,
						upload,
						library,
					}),
				);
			}
		}

		if (!readonly) {
			const drop = create('div', 'drop');
			drop.setAttribute('aria-hidden', 'true');
			drop.append(
				svg('cloud-upload'),
				' ',
				!multiple && !empty ? __('upload:drop-to-replace') : __('media:drop-to-upload'),
			);
			frame.append(drop, this.#input(multiple));
			this.#dropzone(frame, multiple);
		}

		this.replaceChildren(...nodes);
		restore?.();
	}

	/**
	 * @param {number} count
	 * @param {{ multiple: boolean, empty: boolean, full: boolean, loading: boolean, library: () => void, upload: () => void }} state
	 * @returns {HTMLElement}
	 */
	#bar(count, { multiple, empty, full, loading, library, upload }) {
		const max = this.#max;
		const bar = create('div', 'bar');
		const browse = button('browse cms-button secondary small', '', library);
		browse.append(svg('folder2-open'), ' ', __('media:browse'));
		browse.disabled = full;
		const allowed = cosray().system().allowedFiles[this.#type];
		const choose = button(
			'choose',
			multiple ? __('upload:choose-files') : __('upload:choose-file'),
			upload,
		);
		choose.title = `${__('upload:allowed-extensions')} ${allowed.join(', ')}`;
		choose.disabled = full;
		const text = create('span');
		text.append(__('upload:drop-here'), ' ', choose, '.');
		const hint = create('span', 'hint');
		hint.append(svg('cloud-upload'), text);
		bar.append(browse, hint);
		let tally = '';

		if (loading) {
			tally = __('upload:uploading');
		} else if (multiple && !empty) {
			tally =
				max > 1
					? `${count} / ${max}`
					: this.#type === 'image'
						? count === 1
							? __('image:count-one', { count: 1 })
							: __('image:count-many', { count })
						: __('media:file-count', { count });
		}

		if (tally) {
			bar.append(create('span', 'tally', tally));
		}

		return bar;
	}

	/**
	 * A single image as a card, or an image or video as a block's figure.
	 *
	 * @param {HTMLElement} body
	 * @param {FileItem} item
	 * @param {{ block: boolean, loading: boolean, readonly: boolean, store: (items: FileItem[]) => void, removeAll: () => void, upload: () => void, library: () => void }} options
	 */
	#single(body, item, { block, loading, readonly, store, removeAll, upload, library }) {
		const type = this.#type === 'video' ? 'video' : 'image';
		const translate = this.#translate;
		const locales = this.locales;
		const info = this.#asset(item.uid ?? '');
		let current = item;
		/** @type {((item: FileItem, locale: string) => void) | null} */
		let caption = null;
		const form = metaFields({
			item,
			kind: type,
			translate,
			contentLocale: this.#locale,
			locales,
			catalog: info?.meta,
			update: (next) => {
				current = next;
				store([next]);
				caption?.(next, this.#locale);
			},
			readonly,
		});
		this.#followers.push(form.setLocale);

		if (!block) {
			body.append(
				imageCard({
					item,
					info,
					loading,
					readonly,
					meta: form.element,
					preview: () => {
						const url = info?.previewUrl ?? info?.url;

						if (url) previewDialog(this, url);
					},
					remove: removeAll,
					upload,
					library,
				}),
			);

			return;
		}

		const shown = figure({
			type,
			info,
			filename: info?.filename ?? item.uid ?? '',
			caption:
				type === 'image' ? effectiveCaption(item, info, translate, this.#locale, locales) : '',
			loading,
			readonly,
			remove: removeAll,
			upload,
			library,
		});

		if (type === 'image') {
			caption = (next, locale) =>
				shown.setCaption(effectiveCaption(next, info, translate, locale, locales));
			this.#followers.push((locale) => caption?.(current, locale));
		}

		body.append(shown.element);
		// A settings dialog's slot takes the per-use form when the owner
		// offers one; it sits below the media otherwise.
		const settings = create('div', 'cms-figure-settings');

		if (this.settings && translate && locales && locales.all.length > 1) {
			const mirror = contentLocales(locales.all, this.#locale);
			settings.append(mirror.element);
			this.#followers.push(mirror.setLocale);
		}

		settings.append(form.element);

		if (!this.#slot(settings)) {
			shown.element.append(settings);
		}
	}

	/**
	 * The files of a file or video field as rows; a video shows its player
	 * above them.
	 *
	 * @param {string} identity
	 * @param {FileItem[]} items
	 * @param {{ multiple: boolean, loading: boolean, readonly: boolean, removeAt: (index: number) => void, move: (from: number, to: number) => void, upload: () => void, library: () => void }} options
	 * @returns {HTMLElement}
	 */
	#rows(identity, items, { multiple, loading, readonly, removeAt, move, upload, library }) {
		const list = create('div', 'cms-file-list');
		list.append(
			...items.map((item) => {
				const row = fileRow({
					item,
					info: this.#asset(item.uid ?? ''),
					translate: this.#translate,
					contentLocale: this.#locale,
					loading,
					inert: readonly,
					edit: () => this.#edit(identity, position(row)),
					remove: () => removeAt(position(row)),
					replace: multiple ? undefined : { upload, library },
				});

				return row;
			}),
		);
		// A title shown in a row is the current language's. The rows stay:
		// an open dialog returns focus to the one it was opened from.
		this.#followers.push((locale) => {
			for (const [index, row] of Array.from(list.children).entries()) {
				retitle(row, items[index], this.#translate, locale);
			}
		});

		if (!readonly) {
			const sorter = Sortable.create(list, {
				animation: 200,
				onUpdate(event) {
					if (event.oldIndex !== undefined && event.newIndex !== undefined) {
						move(event.oldIndex, event.newIndex);
					}
				},
			});
			this.#teardown.push(() => sorter.destroy());
		}

		return list;
	}

	/**
	 * @param {boolean} multiple
	 * @returns {HTMLInputElement}
	 */
	#input(multiple) {
		const picker = document.createElement('input');
		picker.type = 'file';
		picker.id = this.field.name ?? this.#type;
		picker.multiple = multiple;
		picker.accept = cosray()
			.system()
			.allowedFiles[this.#type].map((suffix) => `.${suffix}`)
			.join(',');
		picker.addEventListener('input', () => {
			const files = picker.files ? [...picker.files] : [];
			picker.value = '';
			void this.#upload(files);
		});
		this.#picker = picker;

		return picker;
	}

	/**
	 * The frame's children fire their own enter/leave pairs, so the drop
	 * state stays up until the pointer has left every one of them.
	 *
	 * @param {HTMLElement} frame
	 * @param {boolean} multiple
	 */
	#dropzone(frame, multiple) {
		/** @param {boolean} on */
		const dragging = (on) => frame.classList.toggle('is-dragging', on);

		frame.addEventListener('dragenter', (event) => {
			if (carriesFiles(event)) {
				event.preventDefault();
				this.#depth += 1;
				dragging(true);
			}
		});
		frame.addEventListener('dragover', (event) => {
			if (carriesFiles(event)) {
				event.preventDefault();
			}
		});
		frame.addEventListener('dragleave', () => {
			this.#depth = Math.max(0, this.#depth - 1);
			dragging(this.#depth > 0);
		});
		frame.addEventListener('drop', (event) => {
			event.preventDefault();
			this.#depth = 0;
			dragging(false);
			const transfer = event.dataTransfer;

			if (!transfer) {
				return;
			}

			const files = transfer.files.length
				? [...transfer.files]
				: [...transfer.items]
						.filter((item) => item.kind === 'file')
						.map((item) => item.getAsFile())
						.filter((file) => file !== null);

			if (!multiple && files.length > 1) {
				alertDialog(this, __('upload:single-only'));

				return;
			}

			void this.#upload(files);
		});
	}

	/**
	 * @param {string} identity
	 * @param {File[]} files
	 * @returns {File[]}
	 */
	#limit(identity, files) {
		const max = this.#max;

		if (max < 1) {
			return files;
		}

		// A single-item field replaces what it holds.
		if (max === 1) {
			return files.slice(0, 1);
		}

		const left = Math.max(max - (this.#map[identity]?.length ?? 0), 0);

		if (left === 0) {
			alertDialog(this, __('upload:max-files', { max }));

			return [];
		}

		if (files.length > left) {
			alertDialog(this, __('upload:slots-left', { count: left }));

			return files.slice(0, left);
		}

		return files;
	}

	/**
	 * Uploads stay bound to the list they started in, even when the
	 * language switches meanwhile.
	 *
	 * @param {File[]} chosen
	 */
	async #upload(chosen) {
		const identity = this.#identity(this.#locale);
		const files = this.#limit(identity, chosen);

		if (files.length === 0) {
			return;
		}

		const type = this.#type;
		this.#pending.set(identity, (this.#pending.get(identity) ?? 0) + 1);

		if (this.#rendered) {
			this.#render();
		}

		try {
			const results = await Promise.all(files.map((file) => cosray().upload(type, file)));

			for (const result of results) {
				if (!result) {
					continue;
				}

				if (!result.ok || !result.uid) {
					cosray().toast.error(
						`${__('upload:file-label')} ${result.filename ?? ''}: ${result.error ?? ''}`,
					);
					continue;
				}

				this.#picked[result.uid] = {
					filename: result.filename ?? '',
					url: result.url ?? '',
					thumbUrl: result.thumbUrl,
					previewUrl: result.previewUrl,
					kind: type,
					mime: result.mime,
					bytes: result.bytes,
					width: result.width,
					height: result.height,
				};
				this.#add(identity, { uid: result.uid });
			}
		} finally {
			this.#pending.set(identity, (this.#pending.get(identity) ?? 1) - 1);
			this.#notify();

			if (this.#rendered) {
				this.#render();
			}
		}
	}

	/** @param {string} identity */
	#library(identity) {
		libraryDialog(this, this.#type, (item) => {
			const max = this.#max;

			// A single field replaces its file; a full one takes no more.
			if (max > 1 && (this.#map[identity]?.length ?? 0) >= max) {
				alertDialog(this, __('upload:max-files', { max }));

				return;
			}

			this.#picked[item.uid] = item;
			this.#add(identity, { uid: item.uid });
			this.#notify();
			this.#render();
		});
	}

	/**
	 * Edits a row's texts in a dialog that follows the content language
	 * while it is open.
	 *
	 * @param {string} identity
	 * @param {number} index
	 */
	#edit(identity, index) {
		const item = this.#map[identity]?.[index];

		if (!item) {
			return;
		}

		const dialog = editDialog({
			owner: this,
			item,
			kind: this.#type === 'video' ? 'video' : 'file',
			translate: this.#translate,
			contentLocale: this.#locale,
			locales: this.locales,
			catalog: this.#asset(item.uid ?? '')?.meta,
			apply: (next) => {
				const list = this.#map[identity];

				if (!list?.[index]) {
					return;
				}

				// Empty per-use meta is dropped so catalog defaults apply.
				list[index] = pruneItemMeta(next);
				this.#notify();

				if (this.#rendered) {
					this.#render();
				}
			},
		});
		this.#dialogs.add(dialog.setLocale);
		void dialog.closed.then(() => this.#dialogs.delete(dialog.setLocale));
	}
}

export class CosrayImage extends MediaControl {
	constructor() {
		super('image');
	}
}

export class CosrayFile extends MediaControl {
	constructor() {
		super('file');
	}
}

export class CosrayVideo extends MediaControl {
	constructor() {
		super('video');
	}
}

for (const [tag, element] of /** @type {const} */ ([
	['cosray-image', CosrayImage],
	['cosray-file', CosrayFile],
	['cosray-video', CosrayVideo],
])) {
	if (!customElements.get(tag)) {
		customElements.define(tag, element);
	}
}

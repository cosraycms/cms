// The gallery of an image field that takes several images: tiles to pick,
// drag and remove, and a drawer with the picked image's texts. In a block
// the tiles stand alone and the drawer and the gallery settings go into
// the settings slot.

/** @import { AssetInfo, FileItem, Meta } from '../../types/data' */
/** @import { ControlLocales } from '../../lib/control.js' */

import Sortable from 'sortablejs';
import { ZXX } from '../../lib/content.js';
import { RATIOS, afterMove, afterRemove, readRatio } from '../../lib/gallery.js';
import { assetLine, extension } from '../../lib/library.js';
import { __ } from '../../lib/locale.js';
import { metaFields } from './meta-fields.js';
import { button, contentLocales, create } from './views.js';

let ids = 0;

/**
 * @typedef {object} GalleryOptions
 * @property {FileItem[]} items The field's list; the owner changes it in place on a move or an
 *     edit and renders anew after anything else.
 * @property {(uid: string) => AssetInfo | undefined} asset
 * @property {boolean} loading
 * @property {boolean} translate
 * @property {string} contentLocale
 * @property {ControlLocales | undefined} locales
 * @property {boolean} open False once the field's limit is reached; hides the add actions.
 * @property {boolean} readonly
 * @property {boolean} block
 * @property {Meta | undefined} meta The gallery settings, ratio and crop, kept as the field's meta.
 * @property {number | null} selected
 * @property {(selected: number | null) => void} select Keeps the selection across renders.
 * @property {(index: number, item: FileItem) => void} update
 * @property {(meta: Meta) => void} updateMeta
 * @property {(index: number) => void} remove
 * @property {(from: number, to: number) => void} move
 * @property {() => void} upload
 * @property {() => void} library
 */

/**
 * @typedef {object} GalleryView
 * @property {HTMLElement} element
 * @property {HTMLElement | null} slot The drawer and the settings of a block gallery, for the
 *     owner to place into the settings slot or after the element.
 * @property {(locale: string) => void} setLocale
 * @property {() => void} destroy
 */

/**
 * Puts parts in order after `anchor`, or first, moving only those out of
 * place: a moved node loses focus.
 *
 * @param {Element} container
 * @param {Element | null} anchor
 * @param {Element[]} parts
 */
function arrange(container, anchor, parts) {
	let previous = anchor;

	for (const part of parts) {
		if (part !== (previous ? previous.nextElementSibling : container.firstElementChild)) {
			if (previous) previous.after(part);
			else container.prepend(part);
		}

		previous = part;
	}
}

/**
 * @param {GalleryOptions} options
 * @returns {GalleryView}
 */
export function galleryView(options) {
	const { items, asset, readonly, block } = options;
	const id = `cms-gallery-${++ids}`;
	let locale = options.contentLocale;
	let meta = options.meta;
	let selected = options.selected;
	let ratio = readRatio(meta?.ratio?.[ZXX]);
	let crop = meta?.crop?.[ZXX] === true;
	/** @type {((locale: string) => void)[]} */
	const followers = [];

	const root = create('div', block ? 'cms-gallery is-block' : 'cms-gallery');
	const tiles = create('div', 'tiles');
	const drawer = create('div', 'drawer');
	const slot = block ? create('div', 'cms-gallery-settings') : null;
	const strip = create('div', 'strip');
	/** @type {Sortable | null} */
	let sorter = null;

	/** @param {FileItem} item */
	const filename = (item) => asset(item.uid ?? '')?.filename ?? item.uid ?? '';

	/** @param {FileItem} item */
	const thumb = (item) => {
		const info = asset(item.uid ?? '');

		return info?.thumbUrl ?? info?.url ?? '';
	};

	// The dialog's drawer always shows an image; the inline drawer opens on a pick.
	const current = () => selected ?? (block && items.length > 0 ? 0 : null);

	/**
	 * @param {FileItem} item
	 * @param {HTMLElement} box
	 */
	function picture(item, box) {
		const source = thumb(item);

		if (source) {
			const image = document.createElement('img');
			image.src = source;
			image.alt = '';
			image.loading = 'lazy';
			box.append(image);
		} else {
			box.append(create('span', 'plate', extension(filename(item))));
		}
	}

	/**
	 * A tile's or thumb's index at the moment, as a drag may have moved it.
	 *
	 * @param {Element} child
	 * @param {Element} [parent]
	 */
	const position = (child, parent = tiles) => Array.prototype.indexOf.call(parent.children, child);

	/** @param {number | null} next */
	function choose(next) {
		selected = next;
		options.select(next);
		sync();
	}

	for (const item of items) {
		const tile = create('div', 'tile');
		tile.title = filename(item);
		const pick = button('pick', '', () => {
			const at = position(tile);
			choose(block || selected !== at ? at : null);
		});
		picture(item, pick);
		tile.append(pick);

		if (!readonly) {
			tile.append(
				button(
					'discard',
					__('common:remove'),
					() => {
						const at = position(tile);
						options.select(afterRemove(selected, at, items.length - 1));
						options.remove(at);
					},
					'x-lg',
				),
			);
		}

		tiles.append(tile);
	}

	function layout() {
		tiles.classList.toggle('has-ratio', ratio !== 'auto');
		tiles.classList.toggle('is-cropped', crop);

		if (ratio !== 'auto') tiles.style.setProperty('--ratio', ratio);
		else tiles.style.removeProperty('--ratio');
	}

	layout();

	if (items.length > 0) {
		const viewport = create('div', 'viewport');
		viewport.append(tiles);
		root.append(viewport);

		if (block && options.open && !readonly) {
			const bar = create('div', 'bar');
			const count =
				items.length === 1
					? __('image:count-one', { count: 1 })
					: __('image:count-many', { count: items.length });
			bar.append(
				create('span', 'status', options.loading ? __('upload:uploading') : count),
				button('quiet', __('media:choose-from-library'), options.library),
				button('quiet', __('image:add'), options.upload),
			);
			root.append(bar);
		}
	}

	if (!readonly) {
		sorter = Sortable.create(tiles, {
			animation: 200,
			onUpdate(event) {
				if (event.oldIndex === undefined || event.newIndex === undefined) {
					return;
				}

				// Sortable has moved the tile already; the list follows.
				options.move(event.oldIndex, event.newIndex);
				selected = afterMove(selected, event.oldIndex, event.newIndex);
				options.select(selected);
				fillStrip();
				sync();
			},
		});
	}

	// The drawer: a head that stays while stepping, so its buttons keep
	// focus, and the texts of the current image below it.
	const head = create('div', 'drawer-head');
	const name = create('span', 'filename');
	const place = create('span', 'position');
	const facts = create('div', 'facts');
	/** @param {number} delta */
	const step = (delta) => {
		const at = current();

		if (at !== null && items.length > 0) choose((at + delta + items.length) % items.length);
	};
	const stepper = create('span', 'stepper');
	stepper.append(
		place,
		button('step prev', __('image:previous'), () => step(-1), 'chevron-left'),
		button('step next', __('image:next'), () => step(1), 'chevron-right'),
	);

	if (!block) {
		stepper.append(button('dismiss', __('common:close'), () => choose(null), 'x-lg'));
		head.append(create('span', 'mini'));
	}

	head.append(name, stepper);
	/** @type {ReturnType<typeof metaFields> | null} */
	let form = null;
	/** @type {string | null} */
	let shown = null;

	/** Thumbs to pick from in the slot, in the order of the tiles. */
	function fillStrip() {
		if (!slot) return;

		strip.replaceChildren(
			...items.map((item) => {
				const thumbButton = button('thumb', '', () => choose(position(thumbButton, strip)));
				thumbButton.title = filename(item);
				picture(item, thumbButton);

				return thumbButton;
			}),
		);
	}

	/** Brings the drawer, the strip and the tiles' marks in line with the selection. */
	function sync() {
		const at = current();
		const item = at === null ? null : items[at];

		for (const [index, tile] of Array.from(tiles.children).entries()) {
			tile.classList.toggle('is-selected', selected === index);
		}

		for (const [index, thumbButton] of Array.from(strip.children).entries()) {
			thumbButton.classList.toggle('is-current', index === at);
		}

		if (at === null || !item) {
			for (const part of [drawer, strip, head, facts]) part.remove();

			form?.element.remove();
			form = null;
			shown = null;

			return;
		}

		if (!block) {
			const source = thumb(item);
			/** @type {HTMLElement} */
			let mini = create('span', 'mini');

			if (source) {
				const image = document.createElement('img');
				image.className = 'mini';
				image.src = source;
				image.alt = '';
				mini = image;
			}

			head.firstElementChild?.replaceWith(mini);
		}

		name.textContent = filename(item);
		name.title = filename(item);
		place.textContent = `${at + 1} / ${items.length}`;
		const info = asset(item.uid ?? '');
		const line = info ? assetLine(info) : '';
		facts.textContent = line;
		const key = `${at}:${item.uid}`;

		if (key !== shown || !form) {
			form?.element.remove();
			form = metaFields({
				item,
				kind: 'image',
				translate: options.translate,
				contentLocale: locale,
				locales: options.locales,
				catalog: info?.meta,
				// The index of the moment: a drag may have moved the image since.
				update: (next) => {
					const now = current();

					if (now !== null) options.update(now, next);
				},
				readonly,
			});
			shown = key;
		}

		if (line === '') facts.remove();

		const parts = [head, ...(line === '' ? [] : [facts]), form.element];

		if (slot) {
			arrange(slot, slot.querySelector(':scope > .options'), [strip, ...parts]);
		} else {
			arrange(drawer, null, parts);

			if (drawer.parentElement !== root) root.append(drawer);
		}
	}

	if (slot) {
		if (options.translate && options.locales && options.locales.all.length > 1) {
			const mirror = contentLocales(options.locales.all, locale);
			slot.append(mirror.element);
			followers.push(mirror.setLocale);
		}

		const settings = create('div', 'options');
		const label = document.createElement('label');
		label.className = 'option';
		label.htmlFor = `${id}-ratio`;
		const select = document.createElement('select');
		select.className = 'cms-select';
		select.id = `${id}-ratio`;
		select.disabled = readonly;

		for (const value of RATIOS) {
			select.append(new Option(value === 'auto' ? __('image:ratio-auto') : value, value));
		}

		select.value = ratio;
		label.append(create('span', undefined, __('image:ratio')), select);
		const check = document.createElement('label');
		check.className = 'check';
		const box = document.createElement('input');
		box.type = 'checkbox';
		box.checked = crop;
		box.disabled = readonly;
		check.append(box, create('span', undefined, __('image:crop')));
		settings.append(label, check);
		slot.append(settings);

		// Auto and crop-off are the site's defaults and are not stored.
		const store = () => {
			if (readonly) return;

			ratio = readRatio(select.value);
			crop = box.checked;
			layout();
			/** @type {Meta} */
			const next = { ...meta };
			delete next.ratio;
			delete next.crop;

			if (ratio !== 'auto') next.ratio = { [ZXX]: ratio };
			if (crop) next.crop = { [ZXX]: true };

			meta = next;
			options.updateMeta(next);
		};

		select.addEventListener('change', store);
		box.addEventListener('change', store);
		fillStrip();
	}

	sync();

	return {
		element: root,
		slot,
		setLocale(next) {
			locale = next;

			for (const follow of followers) follow(next);

			form?.setLocale(next);
		},
		destroy() {
			sorter?.destroy();
			slot?.remove();
		},
	};
}

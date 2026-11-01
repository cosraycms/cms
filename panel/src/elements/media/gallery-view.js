// The gallery of an image field that takes several images: tiles to open,
// drag and remove. In a block the tiles stand alone and the gallery settings
// go into the settings slot.

/** @import { AssetInfo, FileItem, Meta } from '../../types/data' */

import Sortable from 'sortablejs';
import { ZXX } from '../../lib/content.js';
import { RATIOS, readRatio } from '../../lib/gallery.js';
import { extension } from '../../lib/library.js';
import { __, __n } from '../../lib/locale.js';
import { galleryFold } from './gallery-fold.js';
import { button, create, rendition } from './views.js';

let ids = 0;

/**
 * @typedef {object} GalleryOptions
 * @property {FileItem[]} items The field's list; the owner changes it in place on a move and renders
 *     anew after anything else.
 * @property {(uid: string) => AssetInfo | undefined} asset
 * @property {boolean} loading
 * @property {boolean} open False once the field's limit is reached; hides the add actions.
 * @property {boolean} readonly
 * @property {boolean} block
 * @property {boolean} expanded Whether a gallery past two rows starts unfolded.
 * @property {(expanded: boolean) => void} expand
 * @property {Meta | undefined} meta The gallery settings, ratio and crop, kept as the field's meta.
 * @property {(meta: Meta) => void} updateMeta
 * @property {(index: number) => void} edit Opens an image's details.
 * @property {(index: number) => void} remove
 * @property {(from: number, to: number) => void} move
 * @property {() => void} upload
 * @property {() => void} library
 */

/**
 * @typedef {object} GalleryView
 * @property {HTMLElement} element
 * @property {HTMLElement | null} slot The settings of a block gallery, for the owner to place into
 *     the settings slot or after the element.
 * @property {() => void} destroy
 */

/**
 * @param {GalleryOptions} options
 * @returns {GalleryView}
 */
export function galleryView(options) {
	const { items, asset, readonly, block } = options;
	const id = `cms-gallery-${++ids}`;
	let meta = options.meta;
	let ratio = readRatio(meta?.ratio?.[ZXX]);
	let crop = meta?.crop?.[ZXX] === true;

	const root = create('div', block ? 'cms-gallery is-block' : 'cms-gallery');
	const tiles = create('div', 'tiles');
	const slot = block ? create('div', 'cms-gallery-settings') : null;
	/** @type {Sortable | null} */
	let sorter = null;
	/** @type {ReturnType<typeof galleryFold> | null} */
	let fold = null;

	/**
	 * A tile's index at the moment, as a drag may have moved it.
	 *
	 * @param {Element} tile
	 */
	const position = (tile) => Array.prototype.indexOf.call(tiles.children, tile);

	for (const item of items) {
		const info = asset(item.uid ?? '');
		const filename = info?.filename ?? item.uid ?? '';
		const source = info?.thumbUrl ?? info?.url ?? '';
		const tile = create('div', 'tile');
		tile.title = filename;
		const edit = () => options.edit(position(tile));
		// A pointer shortcut; the edit button is the way there for the keyboard.
		const picture = create('div', 'picture');
		picture.addEventListener('click', edit);

		if (source) {
			const image = rendition(source, info?.url);
			image.loading = 'lazy';
			picture.append(image);
		} else {
			picture.append(create('span', 'plate', extension(filename)));
		}

		const tools = create('div', 'tile-tools');
		tools.append(
			button(
				'edit',
				`${readonly ? __('image:details') : __('common:edit')}: ${filename}`,
				edit,
				readonly ? 'eye' : 'pencil',
			),
		);

		if (!readonly) {
			tools.append(
				button(
					'discard',
					`${__('common:remove')}: ${filename}`,
					() => options.remove(position(tile)),
					'x-lg',
				),
			);
		}

		tile.append(picture, tools);
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
		fold = galleryFold({
			viewport,
			tiles,
			expanded: options.expanded,
			changed: options.expand,
		});
		fold.element.setAttribute('aria-controls', (tiles.id = `${id}-tiles`));

		if (block && options.open && !readonly) {
			const bar = create('div', 'bar');
			const count = __n('image:count', 'image:count-plural', items.length);
			bar.append(
				create('span', 'status', options.loading ? __('upload:uploading') : count),
				button('quiet', __('media:choose-from-library'), options.library),
				button('quiet', __('image:add'), options.upload),
			);
			root.append(bar);
		}

		root.append(fold.element);
	}

	if (!readonly) {
		sorter = Sortable.create(tiles, {
			animation: 200,
			onUpdate(event) {
				if (event.oldIndex !== undefined && event.newIndex !== undefined) {
					// Sortable has moved the tile already; the list follows.
					options.move(event.oldIndex, event.newIndex);
					fold?.refresh();
				}
			},
		});
	}

	if (slot) {
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
		slot.append(label, check);

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
	}

	return {
		element: root,
		slot,
		destroy() {
			sorter?.destroy();
			fold?.destroy();
			slot?.remove();
		},
	};
}

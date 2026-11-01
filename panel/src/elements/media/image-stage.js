// The picture of the details dialog as the place to edit it: a pointer or the
// arrow keys set the focal point or draw the crop on the image itself, and
// small previews show what different shapes keep of it.

/** @import { AssetInfo, FileItem } from '../../types/data' */
/** @import { Point, Rect } from '../../lib/image-geometry.js' */

import { RATIOS, readRatio } from '../../lib/gallery.js';
import {
	cleanCrop,
	drawCrop,
	fitCrop,
	moveCrop,
	region,
	resizeCrop,
	showPart,
} from '../../lib/image-geometry.js';
import { __ } from '../../lib/locale.js';
import { button, create, rendition } from './views.js';

const SHAPES = [
	{ ratio: 16 / 9, label: '16:9' },
	{ ratio: 1, label: '1:1' },
	{ ratio: 3 / 4, label: '3:4' },
];
const HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];
// The corners take the keyboard: eight tab stops would only add length.
const CORNERS = new Set(['nw', 'ne', 'se', 'sw']);
const WHOLE = { x: 0, y: 0, w: 1, h: 1 };
const STEP = 0.01;
const LARGE_STEP = 0.1;
// A press that barely moves is a click, which must not start a crop.
const MIN_DRAW = 0.02;

/** @param {number} value */
const fraction = (value) => Math.round(Math.min(1, Math.max(0, value)) * 1000) / 1000;

/** @param {number} value */
const percent = (value) => `${Math.round(value * 100)} %`;

/**
 * @param {KeyboardEvent} event
 * @returns {[number, number] | null}
 */
function arrow(event) {
	const step = event.shiftKey ? LARGE_STEP : STEP;

	switch (event.key) {
		case 'ArrowLeft':
			return [-step, 0];
		case 'ArrowRight':
			return [step, 0];
		case 'ArrowUp':
			return [0, -step];
		case 'ArrowDown':
			return [0, step];
		default:
			return null;
	}
}

/**
 * @typedef {'focus' | 'crop'} StageMode
 */

/**
 * @typedef {object} StageOptions
 * @property {FileItem} item
 * @property {AssetInfo | undefined} info
 * @property {string} source
 * @property {boolean} readonly
 * @property {boolean} cropping Whether the site can deliver a crop of this image.
 * @property {StageMode} mode The tool to start with; stepping through a gallery keeps it.
 * @property {(mode: StageMode) => void} modeChanged
 * @property {(meta: { focal?: Point, crop?: Rect }) => void} change An absent focal point goes
 *     back to the catalog's, an absent crop to the whole image.
 */

/**
 * @param {StageOptions} options
 * @returns {{ nodes: HTMLElement[] }}
 */
export function imageStage({ item, info, source, readonly, cropping, mode, modeChanged, change }) {
	const catalog = info?.meta?.focal;
	let own = item.meta?.focal;
	let crop = item.meta?.crop;
	let width = info?.width ?? 0;
	let height = info?.height ?? 0;
	let tool = cropping ? mode : 'focus';
	/** @type {number | undefined} */
	let lock;
	/** @type {{ ratio: number, box: HTMLElement, image: HTMLImageElement }[]} */
	const shots = [];

	const element = create('div', 'preview');
	const frame = create('div', 'frame');
	const image = rendition(source, info?.url);
	image.draggable = false;
	const marker = create('div', 'marker');
	marker.setAttribute('aria-label', __('image:focus'));
	const shade = create('div', 'shade');
	const hole = create('div', 'hole');
	shade.append(hole);
	const box = create('div', 'crop-box');
	frame.append(image, shade, marker);
	element.append(frame);

	const bar = create('div', 'focus-bar');
	const status = create('div', 'focus-status');
	const text = create('span', 'text');
	status.append(text);
	const reset = button('reset', __('image:focus-reset'), () => {
		if (tool === 'crop') {
			store(undefined);
		} else {
			own = undefined;
			change({ focal: undefined });
			refresh();
		}
	});

	if (!readonly) {
		marker.setAttribute('role', 'slider');
		marker.tabIndex = 0;
		status.append(reset);
	}

	const previews = create('div', 'shots');
	previews.setAttribute('aria-hidden', 'true');

	for (const { ratio, label } of SHAPES) {
		const shot = create('figure', 'shot');
		const cut = create('div', 'box');
		cut.style.aspectRatio = String(ratio);
		const copy = rendition(source, info?.url);
		copy.draggable = false;
		cut.append(copy);
		shot.append(cut, create('figcaption', undefined, label));
		previews.append(shot);
		shots.push({ ratio, box: cut, image: copy });
	}

	bar.append(status, previews);

	/** @type {HTMLElement[]} */
	const nodes = [element, bar];
	const select = document.createElement('select');
	/** @type {HTMLButtonElement[]} */
	const modes = [];
	/** @type {HTMLElement | null} */
	let ratios = null;

	if (cropping) {
		const tools = create('div', 'stage-tools');
		const group = create('div', 'cms-content-locales');
		group.setAttribute('role', 'group');

		for (const [name, label] of /** @type {const} */ ([
			['focus', __('image:focus')],
			['crop', __('image:crop-tool')],
		])) {
			const option = button('option', label, () => {
				tool = name;
				modeChanged(name);
				refresh();
			});
			option.dataset.mode = name;
			group.append(option);
			modes.push(option);
		}

		select.className = 'cms-select';

		for (const ratio of RATIOS) {
			select.append(new Option(ratio === 'auto' ? __('image:ratio-free') : ratio, ratio));
		}

		select.addEventListener('change', () => {
			const ratio = readRatio(select.value);
			lock = ratio === 'auto' ? undefined : lockOf(ratio);

			if (lock) store(fitCrop(crop ?? WHOLE, lock));
		});
		ratios = document.createElement('label');
		ratios.className = 'ratio';
		ratios.append(create('span', undefined, __('image:ratio')), select);
		tools.append(group, ratios);
		nodes.unshift(tools);
	}

	/**
	 * A width to height in fractions of the image, the units a crop is in.
	 *
	 * @param {string} ratio `3/2`
	 */
	function lockOf(ratio) {
		const [w, h] = ratio.split('/').map(Number);

		return (w / h) * (height && width ? height / width : 1);
	}

	/**
	 * A crop's size in the original's pixels; the shown image is a rendition. Stored
	 * dimensions can predate the rotation of a JPEG, which the image already has.
	 *
	 * @param {Rect} part
	 */
	function size(part) {
		const stored = [info?.width ?? width, info?.height ?? height];
		const turned = stored[0] > stored[1] !== width > height;
		const [wide, high] = turned ? [stored[1], stored[0]] : stored;

		return `${Math.round(part.w * wide)} × ${Math.round(part.h * high)} px`;
	}

	/** @param {Rect | undefined} next */
	function store(next) {
		crop = next && cleanCrop(next);
		change({ crop });
		refresh();
	}

	/** @param {HTMLElement} node @param {Rect} part */
	function place(node, part) {
		node.style.left = `${part.x * 100}%`;
		node.style.top = `${part.y * 100}%`;
		node.style.width = `${part.w * 100}%`;
		node.style.height = `${part.h * 100}%`;
	}

	/** The crop tool's parts, as they follow the mode. */
	function arrange() {
		const cropTool = tool === 'crop';
		frame.classList.toggle('is-cropping', cropTool);
		marker.hidden = cropTool;

		for (const option of modes) {
			option.setAttribute('aria-pressed', String(option.dataset.mode === tool));
		}

		if (ratios) ratios.hidden = !cropTool;

		if (cropTool && !box.isConnected) frame.append(box);
		if (!cropTool) box.remove();
	}

	function refresh() {
		arrange();
		const point = own ?? catalog;
		frame.style.setProperty('--ratio', String(width && height ? width / height : 1));
		marker.classList.toggle('is-unset', !point);
		marker.classList.toggle('is-inherited', !own && Boolean(catalog));
		const shown = point ?? { x: 0.5, y: 0.5 };
		marker.style.left = `${shown.x * 100}%`;
		marker.style.top = `${shown.y * 100}%`;
		marker.setAttribute('aria-valuenow', String(Math.round(shown.x * 100)));
		marker.setAttribute('aria-valuetext', `${percent(shown.x)} / ${percent(shown.y)}`);
		shade.hidden = !crop;

		if (crop) place(hole, crop);

		place(box, crop ?? WHOLE);
		box.classList.toggle('is-whole', !crop);

		if (tool === 'crop') {
			reset.hidden = !crop;
			reset.textContent = __('image:crop-remove');
			text.textContent = crop && width && height ? size(crop) : __('image:crop-hint');
		} else {
			reset.hidden = !own;
			reset.textContent = __('image:focus-reset');

			if (point) {
				const where = `${__('image:focus')}: ${percent(point.x)} / ${percent(point.y)}`;
				text.textContent = own ? where : `${where} · ${__('image:from-library')}`;
			} else {
				text.textContent = readonly ? __('image:no-focus') : __('image:focus-set');
			}
		}

		if (width && height) {
			for (const shot of shots) {
				const cut = region(width, height, crop, shown, shot.ratio);
				showPart(shot.box, shot.image, {
					x: cut.x / width,
					y: cut.y / height,
					w: cut.w / width,
					h: cut.h / height,
				});
			}
		}
	}

	/** @param {Point} point */
	function focus(point) {
		own = { x: fraction(point.x), y: fraction(point.y) };
		change({ focal: own });
		refresh();
	}

	/**
	 * A pointer's place on the image, as fractions.
	 *
	 * @param {PointerEvent} event
	 * @returns {Point}
	 */
	function at(event) {
		const rect = frame.getBoundingClientRect();

		return {
			x: Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)),
			y: Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)),
		};
	}

	image.addEventListener('load', () => {
		if (image.naturalWidth && image.naturalHeight) {
			width = image.naturalWidth;
			height = image.naturalHeight;
			refresh();
		}
	});

	if (!readonly) {
		box.tabIndex = 0;
		box.setAttribute('role', 'group');
		box.setAttribute('aria-label', __('image:crop-move'));
		box.dataset.handle = 'move';

		for (const name of HANDLES) {
			const handle = create('div', `handle ${name}`);
			handle.dataset.handle = name;

			if (CORNERS.has(name)) {
				handle.tabIndex = 0;
				handle.setAttribute('role', 'button');
				handle.setAttribute('aria-label', __(`image:crop-${name}`));
			}

			box.append(handle);
		}

		box.addEventListener('keydown', (event) => {
			const move = arrow(event);
			const target = event.target instanceof HTMLElement ? event.target.dataset.handle : undefined;

			if (!move || !target) return;

			event.preventDefault();
			const start = crop ?? WHOLE;
			store(
				target === 'move'
					? moveCrop(start, move[0], move[1])
					: resizeCrop(start, target, move[0], move[1], lock),
			);
		});

		/** @type {((event: PointerEvent) => void) | null} */
		let dragging = null;

		frame.addEventListener('pointerdown', (event) => {
			if (event.button !== 0) return;

			frame.setPointerCapture?.(event.pointerId);
			const point = at(event);

			if (tool === 'focus') {
				marker.focus({ preventScroll: true });
				focus(point);
				dragging = (next) => focus(at(next));
			} else {
				const start = crop;
				const pressed = event.target instanceof HTMLElement ? event.target.dataset.handle : '';
				// A frame around the whole image has nothing to move, so a drag draws.
				const draw = !pressed || (pressed === 'move' && !start);
				dragging = (next) => {
					const to = at(next);
					const dx = to.x - point.x;
					const dy = to.y - point.y;

					if (draw) {
						if (Math.abs(dx) >= MIN_DRAW || Math.abs(dy) >= MIN_DRAW) {
							store(drawCrop(point, to, lock));
						}
					} else if (pressed === 'move') {
						store(moveCrop(/** @type {Rect} */ (start), dx, dy));
					} else {
						store(resizeCrop(start ?? WHOLE, pressed ?? '', dx, dy, lock));
					}
				};
			}

			// Otherwise the press selects the image and takes focus off the marker.
			event.preventDefault();
		});
		frame.addEventListener('pointermove', (event) => dragging?.(event));

		for (const end of ['pointerup', 'pointercancel']) {
			frame.addEventListener(end, () => (dragging = null));
		}

		marker.addEventListener('keydown', (event) => {
			const move = arrow(event);

			if (!move) return;

			event.preventDefault();
			const point = own ?? catalog ?? { x: 0.5, y: 0.5 };
			focus({ x: point.x + move[0], y: point.y + move[1] });
		});
	}

	refresh();

	return { nodes };
}

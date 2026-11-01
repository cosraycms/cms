// The picture of the details dialog as the place to edit it: a pointer or the
// arrow keys set the focal point on the image itself, and small previews show
// what different shapes keep of it.

/** @import { AssetInfo, FileItem } from '../../types/data' */
/** @import { Point } from '../../lib/image-geometry.js' */

import { region, showPart } from '../../lib/image-geometry.js';
import { __ } from '../../lib/locale.js';
import { button, create, rendition } from './views.js';

const SHAPES = [
	{ ratio: 16 / 9, label: '16:9' },
	{ ratio: 1, label: '1:1' },
	{ ratio: 3 / 4, label: '3:4' },
];

const STEP = 0.01;
const LARGE_STEP = 0.1;

/** @param {number} value */
const fraction = (value) => Math.round(Math.min(1, Math.max(0, value)) * 1000) / 1000;

/** @param {number} value */
const percent = (value) => `${Math.round(value * 100)} %`;

/**
 * @typedef {object} StageOptions
 * @property {FileItem} item
 * @property {AssetInfo | undefined} info
 * @property {string} source
 * @property {boolean} readonly
 * @property {(meta: { focal?: Point }) => void} change An absent value goes back to the catalog's.
 */

/**
 * @param {StageOptions} options
 * @returns {{ element: HTMLElement, bar: HTMLElement }}
 */
export function imageStage({ item, info, source, readonly, change }) {
	const catalog = /** @type {Point | undefined} */ (info?.meta?.focal);
	let own = /** @type {Point | undefined} */ (item.meta?.focal);
	let width = info?.width ?? 0;
	let height = info?.height ?? 0;
	/** @type {{ ratio: number, box: HTMLElement, image: HTMLImageElement }[]} */
	const shots = [];

	const element = create('div', 'preview');
	const frame = create('div', 'frame');
	const image = rendition(source, info?.url);
	image.draggable = false;
	const marker = create('div', 'marker');
	marker.setAttribute('aria-label', __('image:focus'));
	frame.append(image, marker);
	element.append(frame);

	const bar = create('div', 'focus-bar');
	const status = create('div', 'focus-status');
	const text = create('span', 'text');
	status.append(text);

	if (!readonly) {
		marker.setAttribute('role', 'slider');
		marker.tabIndex = 0;
		status.append(
			button('reset', __('image:focus-reset'), () => {
				own = undefined;
				change({ focal: undefined });
				refresh();
			}),
		);
	}

	const previews = create('div', 'shots');
	previews.setAttribute('aria-hidden', 'true');

	for (const { ratio, label } of SHAPES) {
		const shot = create('figure', 'shot');
		const box = create('div', 'box');
		box.style.aspectRatio = String(ratio);
		const copy = rendition(source, info?.url);
		copy.draggable = false;
		box.append(copy);
		shot.append(box, create('figcaption', undefined, label));
		previews.append(shot);
		shots.push({ ratio, box, image: copy });
	}

	bar.append(status, previews);

	function refresh() {
		const point = own ?? catalog;
		frame.style.setProperty('--ratio', String(width && height ? width / height : 1));
		marker.classList.toggle('is-unset', !point);
		marker.classList.toggle('is-inherited', !own && Boolean(catalog));
		const shown = point ?? { x: 0.5, y: 0.5 };
		marker.style.left = `${shown.x * 100}%`;
		marker.style.top = `${shown.y * 100}%`;
		marker.setAttribute('aria-valuenow', String(Math.round(shown.x * 100)));
		marker.setAttribute('aria-valuetext', `${percent(shown.x)} / ${percent(shown.y)}`);
		const reset = status.querySelector('.reset');

		if (reset instanceof HTMLElement) reset.hidden = !own;

		if (point) {
			const where = `${__('image:focus')}: ${percent(point.x)} / ${percent(point.y)}`;
			text.textContent = own ? where : `${where} · ${__('image:from-library')}`;
		} else {
			text.textContent = readonly ? __('image:no-focus') : __('image:focus-set');
		}

		for (const shot of shots) {
			if (width && height) {
				const cut = region(width, height, undefined, shown, shot.ratio);
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
	function place(point) {
		own = { x: fraction(point.x), y: fraction(point.y) };
		change({ focal: own });
		refresh();
	}

	image.addEventListener('load', () => {
		if (image.naturalWidth && image.naturalHeight) {
			width = image.naturalWidth;
			height = image.naturalHeight;
			refresh();
		}
	});

	if (!readonly) {
		/** @param {PointerEvent} event */
		const follow = (event) => {
			const box = frame.getBoundingClientRect();
			place({
				x: (event.clientX - box.left) / box.width,
				y: (event.clientY - box.top) / box.height,
			});
		};

		frame.addEventListener('pointerdown', (event) => {
			if (event.button !== 0) return;

			frame.setPointerCapture?.(event.pointerId);
			frame.classList.add('is-dragging');
			marker.focus({ preventScroll: true });
			follow(event);
			// Focus would move to the frame's nearest focusable ancestor otherwise.
			event.preventDefault();
		});
		frame.addEventListener('pointermove', (event) => {
			if (frame.classList.contains('is-dragging')) follow(event);
		});

		for (const end of ['pointerup', 'pointercancel']) {
			frame.addEventListener(end, () => frame.classList.remove('is-dragging'));
		}

		marker.addEventListener('keydown', (event) => {
			const step = event.shiftKey ? LARGE_STEP : STEP;
			const moves = {
				ArrowLeft: [-step, 0],
				ArrowRight: [step, 0],
				ArrowUp: [0, -step],
				ArrowDown: [0, step],
			};
			const move = moves[/** @type {keyof typeof moves} */ (event.key)];

			if (!move) return;

			event.preventDefault();
			const point = own ?? catalog ?? { x: 0.5, y: 0.5 };
			place({ x: point.x + move[0], y: point.y + move[1] });
		});
	}

	refresh();

	return { element, bar };
}

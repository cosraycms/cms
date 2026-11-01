// What a crop, a focal point and a target ratio select from an image, in
// agreement with Assets\Transform, which cuts the renditions:
// contract/image-geometry.json holds them to the same answers.

/**
 * @typedef {{ x: number, y: number }} Point
 * @typedef {{ x: number, y: number, w: number, h: number }} Rect
 */

/** @param {number} value */
const unit = (value) => Math.min(1, Math.max(0, value));

/**
 * The focal point relative to the crop, the image a visitor gets.
 *
 * @param {Rect | undefined} crop
 * @param {Point | undefined} focal
 * @returns {Point | undefined}
 */
export function focalInCrop(crop, focal) {
	if (!focal || !crop) return focal;

	return { x: unit((focal.x - crop.x) / crop.w), y: unit((focal.y - crop.y) / crop.h) };
}

/**
 * The part of an image of the given size to show: the crop, or for a shape of
 * its own ratio the window around the focal point. The window moves by the
 * meaning of CSS `object-position`.
 *
 * @param {number} width
 * @param {number} height
 * @param {Rect | undefined} crop
 * @param {Point | undefined} focal
 * @param {number} [ratio]
 * @returns {Rect} In the units of the size.
 */
export function region(width, height, crop, focal, ratio) {
	const { x: cx, y: cy, w: cw, h: ch } = crop ?? { x: 0, y: 0, w: 1, h: 1 };
	const part = { x: cx * width, y: cy * height, w: cw * width, h: ch * height };

	if (!ratio) return part;

	const point = focalInCrop(crop, focal) ?? { x: 0.5, y: 0.5 };
	const w = Math.min(part.w, part.h * ratio);
	const h = Math.min(part.h, part.w / ratio);

	return { x: part.x + (part.w - w) * point.x, y: part.y + (part.h - h) * point.y, w, h };
}

/**
 * Lets an image show only a part of itself in a box of that part's shape.
 *
 * @param {HTMLElement} box Positioned, clipping its overflow.
 * @param {HTMLImageElement} image
 * @param {Rect} part As fractions of the image.
 */
export function showPart(box, image, part) {
	image.style.position = 'absolute';
	image.style.maxWidth = 'none';
	image.style.maxHeight = 'none';
	image.style.width = `${100 / part.w}%`;
	image.style.height = `${100 / part.h}%`;
	image.style.left = `${(-part.x / part.w) * 100}%`;
	image.style.top = `${(-part.y / part.h) * 100}%`;
	box.style.overflow = 'hidden';
}

const MIN_CROP = 0.02;

/**
 * @param {number} value
 * @param {number} low
 * @param {number} high
 */
const between = (value, low, high) => Math.min(high, Math.max(low, value));

/**
 * The crop after dragging one of its handles.
 *
 * @param {Rect} start
 * @param {string} handle The edges it moves: `n`, `se` and so on.
 * @param {number} dx As a fraction of the image's width.
 * @param {number} dy As a fraction of the image's height.
 * @param {number} [lock] A fixed width to height, in fractions of the image.
 * @returns {Rect}
 */
export function resizeCrop(start, handle, dx, dy, lock) {
	const west = handle.includes('w');
	const east = handle.includes('e');
	const north = handle.includes('n');
	const south = handle.includes('s');
	let left = start.x;
	let top = start.y;
	let right = start.x + start.w;
	let bottom = start.y + start.h;

	if (west) left = between(left + dx, 0, right - MIN_CROP);
	if (east) right = between(right + dx, left + MIN_CROP, 1);
	if (north) top = between(top + dy, 0, bottom - MIN_CROP);
	if (south) bottom = between(bottom + dy, top + MIN_CROP, 1);

	if (!lock) {
		return { x: left, y: top, w: right - left, h: bottom - top };
	}

	// The handle's far side stays where it is; an edge handle grows from the middle.
	const anchorX = west ? right : east ? left : (left + right) / 2;
	const anchorY = north ? bottom : south ? top : (top + bottom) / 2;
	const byWidth = (west || east) && (!(north || south) || Math.abs(dx) >= Math.abs(dy) * lock);
	let w = byWidth ? right - left : (bottom - top) * lock;
	let h = byWidth ? (right - left) / lock : bottom - top;
	const roomX = west ? anchorX : east ? 1 - anchorX : 2 * Math.min(anchorX, 1 - anchorX);
	const roomY = north ? anchorY : south ? 1 - anchorY : 2 * Math.min(anchorY, 1 - anchorY);
	const fit = Math.min(1, roomX / w, roomY / h);
	w *= fit;
	h *= fit;

	return {
		x: west ? anchorX - w : east ? anchorX : anchorX - w / 2,
		y: north ? anchorY - h : south ? anchorY : anchorY - h / 2,
		w,
		h,
	};
}

/**
 * The crop after dragging it as a whole, kept inside the image.
 *
 * @param {Rect} start
 * @param {number} dx
 * @param {number} dy
 * @returns {Rect}
 */
export function moveCrop(start, dx, dy) {
	return {
		...start,
		x: between(start.x + dx, 0, 1 - start.w),
		y: between(start.y + dy, 0, 1 - start.h),
	};
}

/**
 * A new crop drawn from one point to another.
 *
 * @param {Point} from
 * @param {Point} to
 * @param {number} [lock]
 * @returns {Rect}
 */
export function drawCrop(from, to, lock) {
	const handle = `${to.y < from.y ? 'n' : 's'}${to.x < from.x ? 'w' : 'e'}`;

	return resizeCrop({ ...from, w: 0, h: 0 }, handle, to.x - from.x, to.y - from.y, lock);
}

/**
 * The largest part of a crop with a fixed width to height, around its middle.
 *
 * @param {Rect} crop
 * @param {number} lock
 * @returns {Rect}
 */
export function fitCrop(crop, lock) {
	const w = Math.min(crop.w, crop.h * lock);
	const h = Math.min(crop.h, crop.w / lock);

	return { x: crop.x + (crop.w - w) / 2, y: crop.y + (crop.h - h) / 2, w, h };
}

/**
 * A crop as it is stored: rounded, kept inside the image, and gone when it
 * keeps all of it.
 *
 * @param {Rect} crop
 * @returns {Rect | undefined}
 */
export function cleanCrop(crop) {
	/** @param {number} value */
	const round = (value) => Math.round(value * 10000) / 10000;
	const w = round(crop.w);
	const h = round(crop.h);

	if (w >= 1 && h >= 1) return undefined;

	return {
		x: between(round(crop.x), 0, round(1 - w)),
		y: between(round(crop.y), 0, round(1 - h)),
		w,
		h,
	};
}

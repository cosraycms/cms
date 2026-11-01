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
	image.style.width = `${100 / part.w}%`;
	image.style.height = `${100 / part.h}%`;
	image.style.left = `${(-part.x / part.w) * 100}%`;
	image.style.top = `${(-part.y / part.h) * 100}%`;
	box.style.overflow = 'hidden';
}

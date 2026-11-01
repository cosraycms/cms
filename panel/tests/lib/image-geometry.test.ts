import { describe, expect, it } from 'vitest';

import {
	cleanCrop,
	drawCrop,
	fitCrop,
	focalInCrop,
	moveCrop,
	resizeCrop,
} from '../../src/lib/image-geometry.js';

const start = { x: 0.2, y: 0.2, w: 0.4, h: 0.4 };

function near(actual: { x: number; y: number; w: number; h: number }, expected: typeof actual) {
	for (const key of ['x', 'y', 'w', 'h'] as const) {
		expect(actual[key]).toBeCloseTo(expected[key]);
	}
}

describe('resizing a crop', () => {
	it('moves only the edges of the handle', () => {
		near(resizeCrop(start, 'se', 0.1, 0.2), { x: 0.2, y: 0.2, w: 0.5, h: 0.6 });
		near(resizeCrop(start, 'nw', 0.1, 0.1), { x: 0.3, y: 0.3, w: 0.3, h: 0.3 });
		near(resizeCrop(start, 'e', 0.1, 0.3), { x: 0.2, y: 0.2, w: 0.5, h: 0.4 });
		near(resizeCrop(start, 'n', 0.3, -0.1), { x: 0.2, y: 0.1, w: 0.4, h: 0.5 });
	});

	it('stops at the image', () => {
		near(resizeCrop(start, 'nw', -1, -1), { x: 0, y: 0, w: 0.6, h: 0.6 });
		near(resizeCrop(start, 'se', 1, 1), { x: 0.2, y: 0.2, w: 0.8, h: 0.8 });
	});

	it('keeps something of the image when the handle crosses over', () => {
		near(resizeCrop(start, 'se', -1, -1), { x: 0.2, y: 0.2, w: 0.02, h: 0.02 });
	});

	it('keeps a locked shape from a corner', () => {
		const part = resizeCrop(start, 'se', 0.2, 0, 2);

		expect(part.w / part.h).toBeCloseTo(2);
		near(part, { x: 0.2, y: 0.2, w: 0.6, h: 0.3 });
	});

	it('grows a locked shape from the middle of an edge', () => {
		near(resizeCrop(start, 'e', 0.2, 0, 1), { x: 0.2, y: 0.1, w: 0.6, h: 0.6 });
	});

	it('keeps a locked shape inside the image', () => {
		const part = resizeCrop(start, 'se', 1, 0, 1);

		expect(part.w / part.h).toBeCloseTo(1);
		near(part, { x: 0.2, y: 0.2, w: 0.8, h: 0.8 });
	});
});

describe('moving a crop', () => {
	it('stays inside the image', () => {
		near(moveCrop(start, 0.1, -0.1), { x: 0.3, y: 0.1, w: 0.4, h: 0.4 });
		near(moveCrop(start, 1, -1), { x: 0.6, y: 0, w: 0.4, h: 0.4 });
	});
});

describe('drawing a crop', () => {
	it('works in any direction', () => {
		near(drawCrop({ x: 0.5, y: 0.5 }, { x: 0.8, y: 0.9 }), { x: 0.5, y: 0.5, w: 0.3, h: 0.4 });
		near(drawCrop({ x: 0.5, y: 0.5 }, { x: 0.2, y: 0.1 }), { x: 0.2, y: 0.1, w: 0.3, h: 0.4 });
	});

	it('takes a locked shape', () => {
		const part = drawCrop({ x: 0.1, y: 0.1 }, { x: 0.5, y: 0.2 }, 2);

		expect(part.w / part.h).toBeCloseTo(2);
	});
});

describe('fitting a crop to a shape', () => {
	it('takes the largest part around the middle', () => {
		near(fitCrop({ x: 0, y: 0, w: 1, h: 1 }, 2), { x: 0, y: 0.25, w: 1, h: 0.5 });
		near(fitCrop({ x: 0, y: 0, w: 1, h: 1 }, 0.5), { x: 0.25, y: 0, w: 0.5, h: 1 });
	});
});

describe('cleaning a crop', () => {
	it('rounds and keeps it inside the image', () => {
		expect(cleanCrop({ x: 0.33333333, y: 0.700004, w: 0.66666667, h: 0.3 })).toEqual({
			x: 0.3333,
			y: 0.7,
			w: 0.6667,
			h: 0.3,
		});
	});

	it('drops a crop that keeps all of the image', () => {
		expect(cleanCrop({ x: 0, y: 0, w: 1, h: 1 })).toBeUndefined();
	});
});

describe('the focal point in a crop', () => {
	it('is relative to the crop and held at its edge', () => {
		const crop = { x: 0.5, y: 0, w: 0.5, h: 1 };

		expect(focalInCrop(crop, { x: 0.75, y: 0.5 })).toEqual({ x: 0.5, y: 0.5 });
		expect(focalInCrop(crop, { x: 0.1, y: 0.5 })).toEqual({ x: 0, y: 0.5 });
		expect(focalInCrop(undefined, { x: 0.1, y: 0.5 })).toEqual({ x: 0.1, y: 0.5 });
		expect(focalInCrop(crop, undefined)).toBeUndefined();
	});
});

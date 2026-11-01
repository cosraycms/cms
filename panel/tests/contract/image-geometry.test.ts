// JS half of the shared image geometry; the PHP half is
// tests/Unit/AssetTransformTest.php consuming the same fixture file.

import { describe, expect, it } from 'vitest';
import cases from '../../../contract/image-geometry.json';
import { region } from '../../src/lib/image-geometry.js';

type GeometryCase = {
	name: string;
	width: number;
	height: number;
	crop?: { x: number; y: number; w: number; h: number };
	focal?: { x: number; y: number };
	ratio?: number;
	region: { x: number; y: number; w: number; h: number };
};

describe('image geometry', () => {
	it.each(cases as GeometryCase[])('$name', (entry) => {
		const part = region(entry.width, entry.height, entry.crop, entry.focal, entry.ratio);

		expect({
			x: Math.round(part.x),
			y: Math.round(part.y),
			w: Math.round(part.w),
			h: Math.round(part.h),
		}).toEqual(entry.region);
	});
});

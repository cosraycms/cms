import { describe, expect, it } from 'vitest';
import { afterMove, afterRemove } from '../../src/lib/gallery';

describe('gallery selection after a removal', () => {
	it('keeps nothing selected when nothing was', () => {
		expect(afterRemove(null, 0, 3)).toBeNull();
	});

	it('closes the drawer when the last image goes', () => {
		expect(afterRemove(0, 0, 0)).toBeNull();
	});

	it('follows the selected image when an earlier one is removed', () => {
		expect(afterRemove(3, 1, 5)).toBe(2);
	});

	it('stays put when a later image is removed', () => {
		expect(afterRemove(1, 3, 5)).toBe(1);
	});

	it('moves to the successor when the selected image is removed', () => {
		expect(afterRemove(2, 2, 5)).toBe(2);
	});

	it('falls back to the new last image when the removed one was last', () => {
		expect(afterRemove(4, 4, 4)).toBe(3);
	});
});

describe('gallery selection after a reorder', () => {
	it('is untouched without a selection or an actual move', () => {
		expect(afterMove(null, 0, 2)).toBeNull();
		expect(afterMove(2, 1, 1)).toBe(2);
	});

	it('follows the selected image to its new slot', () => {
		expect(afterMove(1, 1, 4)).toBe(4);
		expect(afterMove(4, 4, 0)).toBe(0);
	});

	it('shifts back when an earlier image is dragged past it', () => {
		expect(afterMove(2, 0, 3)).toBe(1);
		expect(afterMove(2, 0, 2)).toBe(1);
	});

	it('shifts forward when a later image is dragged in front of it', () => {
		expect(afterMove(2, 4, 1)).toBe(3);
		expect(afterMove(2, 4, 2)).toBe(3);
	});

	it('ignores moves that stay on one side of it', () => {
		expect(afterMove(2, 3, 4)).toBe(2);
		expect(afterMove(2, 0, 1)).toBe(2);
	});
});

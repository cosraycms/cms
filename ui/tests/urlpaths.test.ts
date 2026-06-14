import type { Node } from '$types/data';
import type { System } from '$lib/sys';

import { describe, expect, it, vi } from 'vitest';
import { generatePaths } from '$lib/urlpaths';

vi.mock('$lib/state', () => ({
	error: vi.fn(),
}));

const system = {
	locales: [{ id: 'en', title: 'English' }],
	transliterate: null,
} as unknown as System;

describe('generatePaths', () => {
	it('supports handle placeholders', () => {
		const node = {
			uid: 'abc123',
			handle: 'downloads',
			content: {},
		} as unknown as Node;

		expect(generatePaths(node, '/{handle}/{uid}', system)).toEqual({
			en: '/downloads/abc123',
		});
	});
});

import type { Node } from '$types/data';

import { beforeEach, describe, expect, it, vi } from 'vitest';
import req from '$lib/req';
import {
	effectiveRoutePath,
	hasExplicitRoutePath,
	isResolvedRoutePath,
	previewRoutePaths,
	routePathPreviewPayload,
	routePathPreviewSignature,
} from '$lib/urlpaths';

vi.mock('$lib/req', () => ({
	default: {
		post: vi.fn(),
	},
}));

const post = vi.mocked(req.post);

describe('route path previews', () => {
	beforeEach(() => {
		post.mockReset();
	});

	it('builds a server preview payload from editable node data', () => {
		const node = {
			uid: 'abc123',
			handle: 'downloads',
			parent: 'parent123',
			content: {
				title: {
					type: 'text',
					value: { en: 'Downloads' },
				},
			},
		} as unknown as Node;

		expect(routePathPreviewPayload(node)).toEqual({
			uid: 'abc123',
			handle: 'downloads',
			parent: 'parent123',
			content: {
				title: {
					type: 'text',
					value: { en: 'Downloads' },
				},
			},
		});
	});

	it('loads generated paths from the server', async () => {
		const payload = {
			uid: 'abc123',
			handle: 'downloads',
			parent: null,
			content: {},
		};
		post.mockResolvedValue({ ok: true, data: { paths: { en: '/downloads' } } });

		await expect(previewRoutePaths('page', payload)).resolves.toEqual({ en: '/downloads' });
		expect(post).toHaveBeenCalledWith('node/page/paths', payload);
	});

	it('returns null when the server rejects a preview', async () => {
		const payload = {
			uid: 'abc123',
			handle: null,
			parent: null,
			content: {},
		};
		post.mockResolvedValue({ ok: false, data: { message: 'Invalid path' } });

		await expect(previewRoutePaths('page', payload)).resolves.toBeNull();
	});

	it('detects explicit route paths', () => {
		expect(hasExplicitRoutePath({ paths: { en: '', de: '  ' } } as unknown as Node)).toBe(
			false,
		);
		expect(hasExplicitRoutePath({ paths: { en: '', de: ' /seite ' } } as unknown as Node)).toBe(
			true,
		);
	});

	it('builds stable route path preview signatures', () => {
		const payload = {
			uid: 'abc123',
			handle: null,
			parent: null,
			content: {},
		};

		expect(routePathPreviewSignature('page', payload)).toBe(
			JSON.stringify({ type: 'page', payload }),
		);
	});

	it('resolves the best available live preview path', () => {
		const node = {
			paths: { en: '', de: '' },
			generatedPaths: { en: '/stations/default', de: '/stations/deutsch' },
		} as unknown as Node;

		expect(effectiveRoutePath(node, 'de', 'en')).toBe('/stations/deutsch');
		expect(effectiveRoutePath(node, 'fr', 'en')).toBe('/stations/default');
	});

	it('prefers explicit preview paths over generated paths', () => {
		const node = {
			paths: { en: '/stations/saved', de: '' },
			generatedPaths: { en: '/stations/generated', de: '/stations/generiert' },
		} as unknown as Node;

		expect(effectiveRoutePath(node, 'en', 'en')).toBe('/stations/saved');
	});

	it('detects incomplete preview paths', () => {
		expect(isResolvedRoutePath('/stations/{title}')).toBe(false);
		expect(isResolvedRoutePath('/stations/title')).toBe(true);
	});
});

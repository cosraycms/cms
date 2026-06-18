import type { Node } from '$types/data';

import { beforeEach, describe, expect, it, vi } from 'vitest';
import req from '$lib/req';
import { previewRoutePaths, routePathPreviewPayload } from '$lib/urlpaths';

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
});

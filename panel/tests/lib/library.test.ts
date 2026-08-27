import { afterEach, describe, expect, it, vi } from 'vitest';
import {
	fetchLibrary,
	humanSize,
	libraryParams,
	readMediaState,
	writeMediaState,
} from '../../src/lib/library';

afterEach(() => {
	vi.unstubAllGlobals();
});

describe('library params', () => {
	it('defaults to the first page of the whole pool', () => {
		expect(libraryParams({}).toString()).toBe('page=1');
	});

	it('restricts to a kind', () => {
		expect(libraryParams({ kind: 'image' }).toString()).toBe('kind=image&page=1');
	});

	it('browses the whole pool for a file context', () => {
		// A File field accepts every kind, so `file` must not restrict.
		expect(libraryParams({ kind: 'file' }).toString()).toBe('page=1');
		expect(libraryParams({ kind: null }).toString()).toBe('page=1');
		expect(libraryParams({ kind: '' }).toString()).toBe('page=1');
	});

	it('trims the search term and drops it when empty', () => {
		expect(libraryParams({ q: '  logo ' }).toString()).toBe('q=logo&page=1');
		expect(libraryParams({ q: '   ' }).toString()).toBe('page=1');
	});

	it('passes the requested page through', () => {
		expect(libraryParams({ kind: 'video', q: 'tour', page: 3 }).toString()).toBe(
			'kind=video&q=tour&page=3',
		);
	});
});

describe('library fetch', () => {
	const item = {
		uid: 'abc123',
		filename: 'beer.jpg',
		url: '/media/image/abc123/beer.jpg',
		thumbUrl: '/media/image/thumb/abc123/beer.jpg',
		kind: 'image',
	};

	it('returns the page and sends the panel request headers', async () => {
		const fetchMock = vi.fn().mockResolvedValue({
			json: () => Promise.resolve({ ok: true, assets: [item], page: 2, more: true, total: 61 }),
		});
		vi.stubGlobal('fetch', fetchMock);

		const result = await fetchLibrary('/panel', { kind: 'image', page: 2 });

		expect(result).toEqual({ items: [item], page: 2, more: true, total: 61 });
		expect(fetchMock).toHaveBeenCalledWith('/panel/media/library?kind=image&page=2', {
			credentials: 'same-origin',
			headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
		});
	});

	it('returns null when the server refuses', async () => {
		vi.stubGlobal(
			'fetch',
			vi.fn().mockResolvedValue({ json: () => Promise.resolve({ ok: false }) }),
		);

		expect(await fetchLibrary('/panel', {})).toBeNull();
	});

	it('returns null on a transport error', async () => {
		vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('offline')));

		expect(await fetchLibrary('/panel', {})).toBeNull();
	});

	it('returns null on an unparseable body', async () => {
		vi.stubGlobal(
			'fetch',
			vi.fn().mockResolvedValue({ json: () => Promise.reject(new SyntaxError('not json')) }),
		);

		expect(await fetchLibrary('/panel', {})).toBeNull();
	});
});

describe('media screen state', () => {
	it('reads state from the query string', () => {
		expect(readMediaState('?kind=image&q=logo&file=abc123')).toEqual({
			kind: 'image',
			q: 'logo',
			file: 'abc123',
		});
	});

	it('defaults an empty or foreign query string', () => {
		expect(readMediaState('')).toEqual({ kind: 'all', q: '', file: null });
		expect(readMediaState('?kind=nonsense&foo=1')).toEqual({ kind: 'all', q: '', file: null });
	});

	it('writes state into the href and drops defaults', () => {
		const href = 'https://example.test/cp/media?kind=video&q=old&file=gone';

		expect(writeMediaState(href, { kind: 'all', q: '  ', file: null })).toBe(
			'https://example.test/cp/media',
		);
		expect(
			writeMediaState('https://example.test/cp/media', { kind: 'image', q: ' logo ', file: 'abc' }),
		).toBe('https://example.test/cp/media?kind=image&q=logo&file=abc');
	});

	it('leaves foreign params untouched', () => {
		expect(
			writeMediaState('https://example.test/cp/media?foo=1', { kind: 'video', q: '', file: null }),
		).toBe('https://example.test/cp/media?foo=1&kind=video');
	});

	it('round-trips through read and write', () => {
		const state = { kind: 'image' as const, q: 'beer', file: 'a1b2' };
		const href = writeMediaState('https://example.test/cp/media', state);

		expect(readMediaState(new URL(href).search)).toEqual(state);
	});
});

describe('human size', () => {
	it('formats bytes with growing units', () => {
		expect(humanSize(0)).toBe('0 B');
		expect(humanSize(512)).toBe('512 B');
		expect(humanSize(2048)).toBe('2.0 KB');
		expect(humanSize(5 * 1024 * 1024)).toBe('5.0 MB');
		expect(humanSize(3.4 * 1024 * 1024 * 1024)).toBe('3.4 GB');
	});
});

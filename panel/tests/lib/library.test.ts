import { afterEach, describe, expect, it, vi } from 'vitest';
import {
	assetLine,
	extension,
	fetchLibrary,
	fileIcon,
	humanSize,
	libraryParams,
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

	it('joins a kind set into a comma list', () => {
		expect(libraryParams({ kind: ['image', 'audio'] }).toString()).toBe(
			'kind=image%2Caudio&page=1',
		);
		expect(libraryParams({ kind: [] }).toString()).toBe('page=1');
	});

	it('passes a created cutoff through', () => {
		expect(libraryParams({ since: '2026-08-20T00:00:00.000Z' }).toString()).toBe(
			'since=2026-08-20T00%3A00%3A00.000Z&page=1',
		);
		expect(libraryParams({ since: null }).toString()).toBe('page=1');
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
		const counts = { image: 61, video: 2, audio: 0, document: 5 };
		const fetchMock = vi.fn().mockResolvedValue({
			json: () =>
				Promise.resolve({ ok: true, assets: [item], page: 2, more: true, total: 61, counts }),
		});
		vi.stubGlobal('fetch', fetchMock);

		const result = await fetchLibrary('/panel', { kind: 'image', page: 2 });

		expect(result).toEqual({ items: [item], page: 2, more: true, total: 61, counts });
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

describe('human size', () => {
	it('formats bytes with growing units', () => {
		expect(humanSize(0)).toBe('0 B');
		expect(humanSize(512)).toBe('512 B');
		expect(humanSize(2048)).toBe('2.0 KB');
		expect(humanSize(5 * 1024 * 1024)).toBe('5.0 MB');
		expect(humanSize(3.4 * 1024 * 1024 * 1024)).toBe('3.4 GB');
	});
});

describe('file icon', () => {
	it('follows the mime type before the extension', () => {
		expect(fileIcon({ filename: 'report.bin', mime: 'application/pdf' })).toBe('file-earmark-pdf');
		expect(fileIcon({ filename: 'talk.mp3', mime: 'audio/mpeg' })).toBe('file-earmark-music');
		expect(fileIcon({ filename: 'clip.dat', mime: 'video/mp4' })).toBe('file-earmark-play');
		expect(
			fileIcon({
				filename: 'x',
				mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			}),
		).toBe('file-earmark-excel');
	});

	it('reads the extension when the mime type is missing or generic', () => {
		expect(fileIcon({ filename: 'terms.DOCX', mime: null })).toBe('file-earmark-word');
		expect(fileIcon({ filename: 'slides.pptx', mime: 'application/octet-stream' })).toBe(
			'file-earmark-ppt',
		);
		expect(fileIcon({ filename: 'backup.tar.gz' })).toBe('file-earmark-zip');
		expect(fileIcon({ filename: 'photo.webp' })).toBe('file-earmark-image');
	});

	it('falls back to the plain sheet', () => {
		expect(fileIcon({ filename: 'README', mime: 'application/octet-stream' })).toBe('file-earmark');
	});
});

describe('asset fact line', () => {
	it('reads the extension off the file name', () => {
		expect(extension('flyer.PDF')).toBe('PDF');
		expect(extension('archive.tar.gz')).toBe('GZ');
		expect(extension('README')).toBe('');
	});

	it('lists pixel dimensions and size for an image', () => {
		expect(assetLine({ filename: 'a.jpg', width: 2400, height: 1600, bytes: 862208 })).toBe(
			'2400 × 1600 px · 842.0 KB',
		);
	});

	it('falls back to the extension when dimensions are unknown', () => {
		expect(assetLine({ filename: 'notes.pdf', bytes: 12 })).toBe('PDF · 12 B');
		expect(assetLine({ filename: 'notes.pdf', width: null, height: null })).toBe('PDF');
	});

	it('is empty when nothing is known', () => {
		expect(assetLine({ filename: 'blob' })).toBe('');
	});
});

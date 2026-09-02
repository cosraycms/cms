import type { AssetInfo } from '$types/data';

/**
 * The asset-catalog listing client shared by every library view: the
 * media screen's grid and the pickers inside editor controls and
 * richtext modals. The `GET {prefix}/media/library` contract lives here
 * and nowhere else.
 */

export type LibraryItem = AssetInfo & { uid: string; thumbUrl: string };

/**
 * The filter vocabulary the library endpoint accepts: it splits the
 * catalog kind `file` into audio and document.
 */
export const FILTER_KINDS = ['image', 'video', 'audio', 'document'] as const;

export type LibraryQuery = {
	// Restricts the listing to one kind or a set of filter kinds; null
	// (and 'file' — a File field accepts every kind) browses the whole
	// pool.
	kind?: string | string[] | null;
	q?: string;
	page?: number;
	// ISO created-timestamp cutoff.
	since?: string | null;
};

export type LibraryPage = {
	items: LibraryItem[];
	page: number;
	more: boolean;
	// Full match count across all pages; 0 when paging past the end.
	total: number;
	// Per-filter-kind totals honoring q and since, but not kind.
	counts: Record<string, number>;
};

export type MediaRange = '' | '7d' | '30d' | 'year';

/**
 * The media screen's deep-linkable state, mirrored into the query string
 * via history.replaceState: kind set, committed search, upload-date
 * range, selected file. The range travels as a token, not a timestamp,
 * so a shared link keeps meaning "the last 7 days".
 */
export type MediaScreenState = {
	kinds: string[];
	q: string;
	range: MediaRange;
	file: string | null;
};

export function readMediaState(search: string): MediaScreenState {
	const params = new URLSearchParams(search);
	const kinds = (params.get('kind') ?? '')
		.split(',')
		.filter((kind): kind is (typeof FILTER_KINDS)[number] =>
			(FILTER_KINDS as readonly string[]).includes(kind),
		);
	const range = params.get('range');

	return {
		kinds: [...new Set(kinds)],
		q: params.get('q') ?? '',
		range: range === '7d' || range === '30d' || range === 'year' ? range : '',
		file: params.get('file'),
	};
}

/** The href with the state written in; foreign params survive untouched. */
export function writeMediaState(href: string, state: MediaScreenState): string {
	const url = new URL(href);
	const params = url.searchParams;
	const q = state.q.trim();

	state.kinds.length === 0 ? params.delete('kind') : params.set('kind', state.kinds.join(','));
	q === '' ? params.delete('q') : params.set('q', q);
	state.range === '' ? params.delete('range') : params.set('range', state.range);
	state.file === null ? params.delete('file') : params.set('file', state.file);

	return url.toString();
}

/** The created-timestamp cutoff a range token stands for right now. */
export function sinceFor(range: MediaRange, now: Date = new Date()): string | null {
	switch (range) {
		case '7d':
			return new Date(now.getTime() - 7 * 86_400_000).toISOString();
		case '30d':
			return new Date(now.getTime() - 30 * 86_400_000).toISOString();
		case 'year':
			return new Date(now.getFullYear(), 0, 1).toISOString();
		default:
			return null;
	}
}

export function humanSize(bytes: number): string {
	const units = ['B', 'KB', 'MB', 'GB'];
	let size = bytes;
	let unit = 0;

	while (size >= 1024 && unit < units.length - 1) {
		size /= 1024;
		unit++;
	}

	return `${unit === 0 ? size : size.toFixed(1)} ${units[unit]}`;
}

export function extension(filename: string): string {
	const dot = filename.lastIndexOf('.');

	return dot === -1 ? '' : filename.slice(dot + 1, dot + 6).toUpperCase();
}

/**
 * The one-line fact row under an asset name: pixel dimensions when
 * known, otherwise the extension, then the size — "2400 × 1600 px · 842 KB".
 */
export function assetLine(
	info: Pick<AssetInfo, 'filename' | 'width' | 'height' | 'bytes'>,
): string {
	const parts: string[] = [];

	if (info.width && info.height) {
		parts.push(`${info.width} × ${info.height} px`);
	} else {
		const suffix = extension(info.filename);

		if (suffix !== '') {
			parts.push(suffix);
		}
	}

	if (typeof info.bytes === 'number') {
		parts.push(humanSize(info.bytes));
	}

	return parts.join(' · ');
}

export function libraryParams(query: LibraryQuery): URLSearchParams {
	const params = new URLSearchParams();
	const raw = query.kind ?? null;
	const kind = Array.isArray(raw) ? raw.filter((entry) => entry !== '').join(',') : raw;
	const q = (query.q ?? '').trim();

	if (kind !== null && kind !== '' && kind !== 'file') {
		params.set('kind', kind);
	}

	if (q !== '') {
		params.set('q', q);
	}

	if (typeof query.since === 'string' && query.since !== '') {
		params.set('since', query.since);
	}

	params.set('page', String(query.page ?? 1));

	return params;
}

/** One catalog page, or null on any transport or server failure. */
export async function fetchLibrary(
	prefix: string,
	query: LibraryQuery,
): Promise<LibraryPage | null> {
	try {
		const response = await fetch(`${prefix}/media/library?${libraryParams(query).toString()}`, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
		});
		const data = (await response.json()) as {
			ok: boolean;
			assets: LibraryItem[];
			page: number;
			more: boolean;
			total: number;
			counts: Record<string, number>;
		};

		if (!data.ok) {
			return null;
		}

		return {
			items: data.assets,
			page: data.page,
			more: data.more,
			total: data.total ?? 0,
			counts: data.counts ?? {},
		};
	} catch {
		return null;
	}
}

import type { AssetInfo } from '$types/data';

/**
 * The asset-catalog listing client shared by every library view: the
 * media screen's grid and the pickers inside editor controls and
 * richtext modals. The `GET {prefix}/media/library` contract lives here
 * and nowhere else.
 */

export type LibraryItem = AssetInfo & { uid: string; thumbUrl: string };

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

const FILE_ICONS: [icon: string, mimes: string[], extensions: string[]][] = [
	['file-earmark-pdf', ['application/pdf'], ['pdf']],
	[
		'file-earmark-word',
		[
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.oasis.opendocument.text',
			'application/rtf',
		],
		['doc', 'docx', 'odt', 'rtf'],
	],
	[
		'file-earmark-excel',
		[
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.oasis.opendocument.spreadsheet',
			'text/csv',
		],
		['xls', 'xlsx', 'ods', 'csv'],
	],
	[
		'file-earmark-ppt',
		[
			'application/vnd.ms-powerpoint',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'application/vnd.oasis.opendocument.presentation',
		],
		['ppt', 'pptx', 'odp'],
	],
	[
		'file-earmark-zip',
		[
			'application/zip',
			'application/x-zip-compressed',
			'application/x-7z-compressed',
			'application/x-rar-compressed',
			'application/vnd.rar',
			'application/gzip',
			'application/x-tar',
		],
		['zip', '7z', 'rar', 'gz', 'tgz', 'tar'],
	],
	['file-earmark-music', ['audio/'], ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'flac', 'aac']],
	['file-earmark-play', ['video/'], ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'avi']],
	['file-earmark-image', ['image/'], ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg']],
	['file-earmark-text', ['text/'], ['txt', 'md']],
];

/**
 * The icon standing in for a file without a thumbnail: by mime type
 * first, by extension second, the plain sheet when neither is known.
 */
export function fileIcon(info: Pick<AssetInfo, 'filename' | 'mime'>): string {
	const mime = (info.mime ?? '').toLowerCase();
	const suffix = extension(info.filename).toLowerCase();

	for (const [icon, mimes] of FILE_ICONS) {
		if (mimes.some((entry) => (entry.endsWith('/') ? mime.startsWith(entry) : mime === entry))) {
			return icon;
		}
	}

	for (const [icon, , extensions] of FILE_ICONS) {
		if (extensions.includes(suffix)) {
			return icon;
		}
	}

	return 'file-earmark';
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

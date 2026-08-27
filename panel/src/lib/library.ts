import type { AssetInfo } from '$types/data';

/**
 * The asset-catalog listing client shared by every library view: the
 * media screen's grid and the pickers inside editor controls and
 * richtext modals. The `GET {prefix}/media/library` contract lives here
 * and nowhere else.
 */

export type LibraryItem = AssetInfo & { uid: string; thumbUrl: string };

export type LibraryQuery = {
	// Restricts the listing to one kind; null (and 'file' — a File field
	// accepts every kind) browses the whole pool.
	kind?: string | null;
	q?: string;
	page?: number;
};

export type LibraryPage = {
	items: LibraryItem[];
	page: number;
	more: boolean;
	// Full match count across all pages; 0 when paging past the end.
	total: number;
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

export function libraryParams(query: LibraryQuery): URLSearchParams {
	const params = new URLSearchParams();
	const kind = query.kind ?? null;
	const q = (query.q ?? '').trim();

	if (kind !== null && kind !== '' && kind !== 'file') {
		params.set('kind', kind);
	}

	if (q !== '') {
		params.set('q', q);
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
		};

		if (!data.ok) {
			return null;
		}

		return { items: data.assets, page: data.page, more: data.more, total: data.total ?? 0 };
	} catch {
		return null;
	}
}

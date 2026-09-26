/** @import { AssetInfo } from '../types/data' */

/**
 * How the panel's scripts state an asset's facts: its size, extension,
 * dimensions and the icon of a file without a thumbnail.
 */

/**
 * @param {number} bytes
 * @returns {string}
 */
export function humanSize(bytes) {
	const units = ['B', 'KB', 'MB', 'GB'];
	let size = bytes;
	let unit = 0;

	while (size >= 1024 && unit < units.length - 1) {
		size /= 1024;
		unit++;
	}

	return `${unit === 0 ? size : size.toFixed(1)} ${units[unit]}`;
}

/** @type {[icon: string, mimes: string[], extensions: string[]][]} */
const FILE_ICONS = [
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
 *
 * @param {Pick<AssetInfo, 'filename' | 'mime'>} info
 * @returns {string}
 */
export function fileIcon(info) {
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

/**
 * @param {string} filename
 * @returns {string}
 */
export function extension(filename) {
	const dot = filename.lastIndexOf('.');

	return dot === -1 ? '' : filename.slice(dot + 1, dot + 6).toUpperCase();
}

/**
 * The one-line fact row under an asset name: pixel dimensions when
 * known, otherwise the extension, then the size — "2400 × 1600 px · 842 KB".
 *
 * @param {Pick<AssetInfo, 'filename' | 'width' | 'height' | 'bytes'>} info
 * @returns {string}
 */
export function assetLine(info) {
	const parts = [];

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

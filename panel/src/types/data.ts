import type { Field } from '$types/fields';

export const ZXX = 'zxx';

export type LocaleMap<T> = Record<string, T>;

// The cosray richtext storage format (docs/richtext-format.md).
export interface RichtextMark {
	type: string;
	attrs?: Record<string, unknown>;
}

export interface RichtextNode {
	type: string;
	attrs?: Record<string, unknown>;
	text?: string;
	marks?: RichtextMark[];
	content?: RichtextNode[];
}

export interface RichtextDoc {
	type: 'doc';
	content: RichtextNode[];
}

export interface User {
	uid: string;
	email: string;
	username: string;
	name: string;
	password: string;
	passwordRepeat: string;
}

export interface Meta {
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	[key: string]: LocaleMap<any>;
}

export interface FileItem {
	uid?: string;
	meta?: Meta;
}

export interface TranslatedFile extends FileItem {
	uid: string;
}

// Resolved catalog data for an asset uid, from the SSR payload, an
// upload response or the library endpoint.
export interface AssetInfo {
	filename: string;
	url: string;
	thumbUrl?: string;
	previewUrl?: string;
	kind: string;
	mime?: string | null;
	bytes?: number | null;
	width?: number | null;
	height?: number | null;
	meta?: Meta;
}

export type AssetMap = Record<string, AssetInfo>;

export interface TextData {
	type: string;
	value: LocaleMap<string>;
	meta?: Meta;
}

export interface CodeData extends TextData {
	meta: Meta & { syntax: LocaleMap<string> };
}

export interface NumberData {
	type: string;
	value: LocaleMap<number | string | null>;
	meta?: Meta;
}

export interface BooleanData {
	type: string;
	value: LocaleMap<boolean | null>;
	meta?: Meta;
}

export interface FileData {
	type: string;
	value: LocaleMap<FileItem[]>;
	meta?: Meta;
}

export interface UploadResponse {
	ok: boolean;
	error: string;
	uid: string;
	filename: string;
	url: string;
	thumbUrl?: string;
	previewUrl?: string;
	mime: string | null;
	width: number | null;
	height: number | null;
}

export type UploadType = 'image' | 'file' | 'video';

export type Data = TextData | CodeData | FileData | NumberData | BooleanData;
export type Content = Record<string, Data>;
export type Route = string | Record<string, string>;

export interface Column {
	value: string | boolean | number;
	bold: boolean;
	italic: boolean;
	badge: boolean;
	date: boolean;
	color: string;
}

export interface ListedNode {
	uid: string;
	published: boolean;
	hidden: boolean;
	locked: boolean;
	parent: string | null;
	hasChildren: boolean;
	childBlueprints: Blueprint[];
	columns: Column[];
}

export interface Blueprint {
	slug: string;
	name: string;
}

export interface Collection {
	name: string;
	slug: string;
	showPublished: boolean;
	showHidden: boolean;
	showLocked: boolean;
	showChildren: boolean;
	header: string[];
	total: number;
	offset: number;
	limit: number;
	q: string;
	sort: string;
	dir: string;
	sorts: string[];
	nodes: ListedNode[];
	blueprints: Blueprint[];
}

export interface Type {
	handle: string;
	class: string;
	routable: boolean;
	renderable: boolean;
}

export interface Editor {
	uid: string;
	email: string;
	username: string;
	data: {
		name: string;
	};
}

export interface Node {
	uid: string;
	handle: string | null;
	title: string;
	published: boolean;
	hidden: boolean;
	locked: boolean;
	parent?: string | null;
	deletable: boolean;
	created: string;
	changed: string;
	deleted: null | string;
	type: Type;
	paths: Record<string, string>;
	generatedPaths: Record<string, string>;
	route?: Route;

	fields: Field[];
	content: Content;

	creator: Editor;
	editor: Editor;
}

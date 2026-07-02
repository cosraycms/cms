import type { Field } from '$types/fields';

export const ZXX = 'zxx';

export type LocaleMap<T> = Record<string, T>;

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
	file?: string;
	meta?: Meta;
}

export interface TranslatedFile extends FileItem {
	file: string;
}

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

export interface GenericFieldData {
	type: string;
	value?: unknown;
	meta?: Meta;
}

export interface FileData {
	type: string;
	value: LocaleMap<FileItem[]>;
	meta?: Meta;
}

export interface UploadResponse {
	ok: boolean;
	file: string;
	error: string;
}

export type UploadType = 'image' | 'file' | 'video';

export interface BlockBase {
	type: string;
	colspan: number;
	rowspan: number;
	colstart?: number | null;
	width?: number | null;
	meta?: Meta;
}

export interface BlockText extends BlockBase {
	type: 'text' | 'richtext' | 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'iframe';
	value: LocaleMap<string>;
}

export interface BlockImage extends BlockBase {
	type: 'image' | 'images' | 'video';
	value: TranslatedFile[];
}

export interface BlockYoutube extends BlockBase {
	type: 'youtube';
	value: LocaleMap<string>;
	meta: Meta & {
		aspectRatioX: LocaleMap<number>;
		aspectRatioY: LocaleMap<number>;
	};
}

export type BlockType =
	| 'text'
	| 'richtext'
	| 'h1'
	| 'h2'
	| 'h3'
	| 'h4'
	| 'h5'
	| 'h6'
	| 'image'
	| 'youtube'
	| 'images'
	| 'video'
	| 'iframe';

export interface BlockCustom extends BlockBase {
	type: string;
	value: unknown;
	meta?: Meta;
}

export type Block = BlockText | BlockImage | BlockYoutube | BlockCustom;
export type BlockImages = BlockImage;
export type BlockVideo = BlockImage;
export type BlockIframe = BlockText;
export type BlockRichText = BlockText;

export interface LocalizedBlocksValue {
	[key: string]: Block[];
}

export interface BlocksData {
	type: string;
	value: LocalizedBlocksValue;
	meta: Meta & {
		columns: LocaleMap<number>;
		minCellWidth?: LocaleMap<number>;
	};
}

export interface EntryData {
	uid: string;
	type: string;
	fields: Record<string, Data | GenericFieldData>;
}

export interface EntriesData {
	type: string;
	value: LocaleMap<EntryData[]>;
	meta?: Meta;
}

export type Data =
	| TextData
	| CodeData
	| FileData
	| BlocksData
	| NumberData
	| BooleanData
	| EntriesData;
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

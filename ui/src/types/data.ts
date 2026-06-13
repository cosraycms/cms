import type { Field } from '$types/fields';

export interface User {
	uid: string;
	email: string;
	username: string;
	name: string;
	password: string;
	passwordRepeat: string;
}

export interface FileItem {
	file?: string;
	alt?: string | Record<string, string>;
	title?: string | Record<string, string>;
}

export interface TranslatedFile {
	file: string;
	alt?: string;
	title?: string;
}

export interface TextData {
	type: 'text' | 'richtext' | 'hidden' | 'date' | 'time' | 'datetime' | 'option' | 'iframe';
	value?: string | Record<string, string>;
}

export interface CodeData {
	type: 'code';
	syntax: string;
	value?: string | Record<string, string>;
}

export interface NumberData {
	type: 'number';
	value?: number;
}

export interface BooleanData {
	type: 'checkbox';
	value?: boolean;
}

export interface GenericFieldData {
	type: string;
	value?: unknown;
	syntax?: string;
	files?: FileItem[] | Record<string, TranslatedFile[]>;
	columns?: number;
}

export interface FileData {
	type: 'picture' | 'image' | 'video';
	files: FileItem[] | Record<string, TranslatedFile[]>;
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
	width?: number | null; // will be added while rendering the blocks
}

export interface BlockText extends BlockBase {
	type: 'text';
	value: string;
}

export interface BlockRichText extends BlockBase {
	type: 'richtext';
	value: string;
}

export interface BlockIframe extends BlockBase {
	type: 'iframe';
	value: string;
}

export interface BlockImage extends BlockBase {
	type: 'image';
	files: TranslatedFile[];
}

export interface BlockImages extends BlockBase {
	type: 'images';
	files: TranslatedFile[];
}

export interface BlockVideo extends BlockBase {
	type: 'video';
	files: TranslatedFile[];
}

export interface BlockYoutube extends BlockBase {
	type: 'youtube';
	value: string;
	aspectRatioX: number;
	aspectRatioY: number;
}

export type BlockType = 'text' | 'richtext' | 'image' | 'youtube' | 'images' | 'video' | 'iframe';

export type Block =
	| BlockText
	| BlockRichText
	| BlockImage
	| BlockImages
	| BlockYoutube
	| BlockVideo
	| BlockIframe;

export interface LocalizedBlocksValue {
	[key: string]: Block[];
}

export interface BlocksData {
	type: 'blocks';
	columns: number;
	value: Block[] | LocalizedBlocksValue;
}

// Entries field types
export interface EntryData {
	[fieldName: string]: Data | GenericFieldData;
}

export interface EntriesData {
	type: 'entries';
	value: EntryData[];
}

export type Data = TextData | CodeData | FileData | BlocksData | NumberData | EntriesData;
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

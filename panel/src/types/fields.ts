import type { ControlDescriptor } from '$types/controls';

export type TranslateMode = 'symmetric' | 'asymmetric';

export interface SimpleField {
	rows: number | null;
	width: number | null;
	required: boolean;
	immutable: boolean;
	hidden: boolean;
	description: string | null;
	label: string;
	name: string;
	type: string;
	control: ControlDescriptor;
	translate: boolean;
	translateMode?: TranslateMode;
	options?: Array<string | { value: string; label: string }>;
}

export interface Limit {
	min: number;
	max: number;
}

export interface FileField extends SimpleField {
	limit?: Limit;
}

export interface ImageField extends FileField {}

export interface BlockTypeMeta {
	id: string;
	label: string;
	control: ControlDescriptor;
	init: Record<string, unknown>;
	hidden: boolean;
}

export interface BlocksField extends SimpleField {
	columns: number;
	minCellWidth: number;
	blockTypes: BlockTypeMeta[];
}

export interface EntryType {
	type: string;
	label: string;
	fields: Field[];
	init: Record<string, unknown>;
}

export interface EntriesField extends SimpleField {
	entryTypes: EntryType[];
}

export interface CodeField extends SimpleField {
	syntaxes?: string[];
}

export type Field = ImageField | FileField | BlocksField | EntriesField | CodeField | SimpleField;

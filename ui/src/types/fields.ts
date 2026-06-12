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
	translate: boolean;
}

export interface Limit {
	min: number;
	max: number;
}

export interface FileField extends SimpleField {
	limit?: Limit;
	translateFile: boolean;
}

export interface ImageField extends FileField {
	translateFile: boolean;
}

export interface BlocksField extends SimpleField {
	columns: number;
	minCellWidth: number;
}

export interface MatrixField extends SimpleField {
	subfields: Field[];
}

export interface CodeField extends SimpleField {
	syntaxes?: string[];
}

export type Field = ImageField | FileField | BlocksField | MatrixField | CodeField | SimpleField;

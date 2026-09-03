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

export type Field = SimpleField;

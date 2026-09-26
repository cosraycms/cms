export interface ControlDescriptor {
	name: string;
	props: Record<string, unknown>;
}

export interface GroupField {
	key: string;
	label?: string;
	control: ControlDescriptor;
}

export interface ElementProps {
	tag: string;
	module: string;
}

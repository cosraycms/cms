export type ModalOptions = {
	hideClose?: boolean;
	label?: string;
	size?: 'compact' | 'wide';
	owner?: HTMLElement;
};

export type UploadResult = {
	ok: boolean;
	error?: string;
	uid?: string;
	filename?: string;
	url?: string;
	thumbUrl?: string;
	previewUrl?: string;
	mime?: string | null;
	bytes?: number | null;
	width?: number | null;
	height?: number | null;
};

export type BridgeSystem = {
	locale: string;
	defaultLocale: string;
	locales: { id: string; title: string; fallback?: string | null }[];
	customLocales: string[];
	prefix: string;
	assets: string;
	debug: boolean;
	allowedFiles: { file: string[]; image: string[]; video: string[] };
	readPermissions?: string[];
};

/**
 * The public runtime API for editor controls implemented as custom
 * elements — cosray's own and plugin-shipped ones alike. Installed by
 * the node editor; only available on panel editor pages.
 */
export type CosrayBridge = {
	version: 1;
	system(): BridgeSystem;
	upload(type: 'image' | 'file' | 'video', file: File): Promise<UploadResult>;
	modal: {
		open(
			render: (host: HTMLElement) => (() => void) | void,
			options?: ModalOptions,
		): { close(): void };
	};
	toast: {
		success(message: string): void;
		error(message: string): void;
	};
};

declare global {
	interface Window {
		Cosray?: CosrayBridge;
	}
}

export function editorType(): string | null {
	return document.querySelector<HTMLElement>('#node-editor-form')?.dataset.nodeType ?? null;
}

export function cosray(): CosrayBridge {
	if (!window.Cosray) {
		throw new Error(
			'window.Cosray is unavailable — editor controls only run on panel editor pages',
		);
	}

	return window.Cosray;
}

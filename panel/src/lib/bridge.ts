export type ModalOptions = {
	hideClose?: boolean;
};

export type UploadResult = {
	ok: boolean;
	error?: string;
	uid?: string;
	filename?: string;
	url?: string;
	mime?: string | null;
	width?: number | null;
	height?: number | null;
};

export type BridgeSystem = {
	locale: string;
	defaultLocale: string;
	locales: { id: string; title: string }[];
	customLocales: string[];
	prefix: string;
	assets: string;
	debug: boolean;
	allowedFiles: { file: string[]; image: string[]; video: string[] };
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

export function cosray(): CosrayBridge {
	if (!window.Cosray) {
		throw new Error(
			'window.Cosray is unavailable — editor controls only run on panel editor pages',
		);
	}

	return window.Cosray;
}

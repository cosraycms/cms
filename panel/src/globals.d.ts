/// <reference types="vite/client" />

export {}; // ensure this file is a module

declare global {
	/** The classic htmx script's global, loaded before every panel module. */
	var htmx: {
		ajax(verb: string, path: string, context?: Record<string, unknown>): Promise<void>;
	};

	interface Window {
		COSRAY_BASE_PATH: string;
		COSRAY_ASSETS_PATH: string;
	}
}

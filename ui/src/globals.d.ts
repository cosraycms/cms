export {}; // ensure this file is a module

declare global {
	interface Window {
		COSRAY_BASE_PATH: string;
	}
}

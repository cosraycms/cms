export {}; // ensure this file is a module

declare global {
	interface Window {
		COSRAY_API_BASE?: string;
		COSRAY_BASE_PATH: string;
		COSRAY_BOOT_URL?: string;
		COSRAY_LOGIN_URL?: string;
	}
}

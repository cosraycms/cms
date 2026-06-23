export type NavigateOptions = {
	invalidateAll?: boolean;
};

export type Navigate = (url: string | URL, options?: NavigateOptions) => void | Promise<void>;

type Runtime = {
	panelBase: string;
	apiBase: string;
	bootUrl: string;
	loginUrl: string;
	navigate: Navigate;
};

function globalString(key: keyof Window, fallback: string): string {
	if (typeof window === 'undefined') {
		return fallback;
	}

	const value = window[key];

	return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}

function withTrailingSlash(path: string): string {
	path = path.trim();

	if (path === '') {
		return '/';
	}

	return path.endsWith('/') ? path : `${path}/`;
}

function withoutTrailingSlash(path: string): string {
	path = path.trim();

	if (path === '/') {
		return path;
	}

	return path.replace(/\/+$/, '');
}

function defaultPanelBase(): string {
	return withTrailingSlash(globalString('COSRAY_BASE_PATH', '/panel/'));
}

function defaultNavigate(url: string | URL): void {
	if (typeof window === 'undefined') {
		return;
	}

	window.location.href = url.toString();
}

const initialPanelBase = defaultPanelBase();

const runtime: Runtime = {
	panelBase: initialPanelBase,
	apiBase: withoutTrailingSlash(
		globalString('COSRAY_API_BASE', `${withoutTrailingSlash(initialPanelBase)}/api`),
	),
	bootUrl: globalString('COSRAY_BOOT_URL', `${withoutTrailingSlash(initialPanelBase)}/boot`),
	loginUrl: globalString('COSRAY_LOGIN_URL', `${initialPanelBase}login`),
	navigate: defaultNavigate,
};

export function configureRuntime(config: Partial<Runtime>): void {
	if (config.panelBase !== undefined) {
		runtime.panelBase = withTrailingSlash(config.panelBase);
	}

	if (config.apiBase !== undefined) {
		runtime.apiBase = withoutTrailingSlash(config.apiBase);
	}

	if (config.bootUrl !== undefined) {
		runtime.bootUrl = config.bootUrl;
	}

	if (config.loginUrl !== undefined) {
		runtime.loginUrl = config.loginUrl;
	}

	if (config.navigate !== undefined) {
		runtime.navigate = config.navigate;
	}
}

export function panelBase(): string {
	return runtime.panelBase;
}

export function apiBase(): string {
	return runtime.apiBase;
}

export function bootUrl(): string {
	return runtime.bootUrl;
}

export function loginUrl(): string {
	return runtime.loginUrl;
}

export async function navigate(url: string | URL, options?: NavigateOptions): Promise<void> {
	await runtime.navigate(url, options);
}

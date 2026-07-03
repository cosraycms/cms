type Runtime = {
	panelBase: string;
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

const runtime: Runtime = {
	panelBase: withTrailingSlash(globalString('COSRAY_BASE_PATH', '/panel/')),
};

export function configureRuntime(config: Partial<Runtime>): void {
	if (config.panelBase !== undefined) {
		runtime.panelBase = withTrailingSlash(config.panelBase);
	}
}

export function panelBase(): string {
	return runtime.panelBase;
}

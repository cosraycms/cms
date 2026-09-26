/**
 * @typedef {object} Runtime
 * @property {string} panelBase
 * @property {string} assetsBase The versioned URL the panel's own files are served under.
 */

/**
 * @param {'COSRAY_BASE_PATH' | 'COSRAY_ASSETS_PATH'} key
 * @param {string} fallback
 * @returns {string}
 */
function globalString(key, fallback) {
	if (typeof window === 'undefined') {
		return fallback;
	}

	const value = window[key];

	return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}

/**
 * @param {string} path
 * @returns {string}
 */
function withTrailingSlash(path) {
	path = path.trim();

	if (path === '') {
		return '/';
	}

	return path.endsWith('/') ? path : `${path}/`;
}

/** @type {Runtime} */
const runtime = {
	panelBase: withTrailingSlash(globalString('COSRAY_BASE_PATH', '/panel/')),
	assetsBase: withTrailingSlash(globalString('COSRAY_ASSETS_PATH', '/panel/assets/dev/')),
};

/** @param {Partial<Runtime>} config */
export function configureRuntime(config) {
	if (config.panelBase !== undefined) {
		runtime.panelBase = withTrailingSlash(config.panelBase);
	}

	if (config.assetsBase !== undefined) {
		runtime.assetsBase = withTrailingSlash(config.assetsBase);
	}
}

/** @returns {string} */
export function panelBase() {
	return runtime.panelBase;
}

/** @returns {string} */
export function assetsBase() {
	return runtime.assetsBase;
}

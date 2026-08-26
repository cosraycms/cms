import { afterEach, describe, expect, it, vi } from 'vitest';
import { moduleUrl } from '../../src/lib/elements';
import { configureRuntime } from '../../src/lib/runtime';

afterEach(() => {
	vi.unstubAllEnvs();
	configureRuntime({ panelBase: '/panel/' });
});

describe('module resolution', () => {
	it('serves cosray-shipped elements from the panel static assets', () => {
		vi.stubEnv('DEV', false);

		expect(moduleUrl('cosray:richtext')).toBe('/panel/static/elements/richtext.js');
	});

	it('resolves cosray-shipped elements against the Vite dev server in dev', () => {
		// Vitest runs in dev mode; the URL is anchored to the module, so
		// only the path shape is stable.
		expect(moduleUrl('cosray:richtext').endsWith('/src/elements/richtext.ts')).toBe(true);
	});

	it('passes full URLs through untouched', () => {
		vi.stubEnv('DEV', false);

		expect(moduleUrl('https://cdn.example.com/x.js')).toBe('https://cdn.example.com/x.js');
		expect(moduleUrl('http://cdn.example.com/x.js')).toBe('http://cdn.example.com/x.js');
	});

	it('serves anything else from the plugin vendor route', () => {
		vi.stubEnv('DEV', false);

		expect(moduleUrl('acme-shop/map.js')).toBe('/panel/vendor/acme-shop/map.js');
	});

	it('follows a reconfigured panel base and normalizes its trailing slash', () => {
		vi.stubEnv('DEV', false);
		configureRuntime({ panelBase: '/cp' });

		expect(moduleUrl('acme-shop/map.js')).toBe('/cp/vendor/acme-shop/map.js');
	});
});

import { afterEach, describe, expect, it } from 'vitest';
import { moduleUrl } from '../../src/lib/elements';
import { configureRuntime } from '../../src/lib/runtime';

afterEach(() => {
	configureRuntime({ panelBase: '/panel/', assetsBase: '/panel/assets/dev/' });
});

describe('module resolution', () => {
	it('serves cosray-shipped elements as modules from the package', () => {
		configureRuntime({ assetsBase: '/cp/assets/0123456789ab' });

		expect(moduleUrl('cosray:code')).toBe('/cp/assets/0123456789ab/src/elements/code.js');
		expect(moduleUrl('cosray:media')).toBe('/cp/assets/0123456789ab/src/elements/media.js');
	});

	it('passes full URLs through untouched', () => {
		expect(moduleUrl('https://cdn.example.com/x.js')).toBe('https://cdn.example.com/x.js');
		expect(moduleUrl('http://cdn.example.com/x.js')).toBe('http://cdn.example.com/x.js');
	});

	it('serves anything else from the plugin vendor route', () => {
		expect(moduleUrl('acme-shop/map.js')).toBe('/panel/vendor/acme-shop/map.js');
	});

	it('follows a reconfigured panel base and normalizes its trailing slash', () => {
		configureRuntime({ panelBase: '/cp' });

		expect(moduleUrl('acme-shop/map.js')).toBe('/cp/vendor/acme-shop/map.js');
	});
});

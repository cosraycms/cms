import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { remember } from '../../src/lib/panel-cookie';

beforeEach(() => {
	history.replaceState(null, '', '/panel/node/test');
});

afterEach(() => {
	document.cookie = 'cosray_panel=; path=/panel; max-age=0';
	history.replaceState(null, '', '/');
});

describe('panel cookie', () => {
	it('keeps every choice in one cookie and forgets them one by one', () => {
		remember('inspector', 'collapsed');
		remember('theme', 'dark');

		expect(document.cookie).toBe('cosray_panel=inspector=collapsed&theme=dark');

		remember('inspector', null);

		expect(document.cookie).toBe('cosray_panel=theme=dark');

		remember('theme', null);

		expect(document.cookie).toBe('');
	});

	it('is sent to the panel only', () => {
		remember('theme', 'dark');
		history.replaceState(null, '', '/about');

		expect(document.cookie).toBe('');
	});
});

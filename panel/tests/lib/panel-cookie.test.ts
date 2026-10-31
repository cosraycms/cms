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
		remember('blocks', 'wide');

		expect(document.cookie).toBe('cosray_panel=inspector=collapsed&blocks=wide');

		remember('inspector', null);

		expect(document.cookie).toBe('cosray_panel=blocks=wide');

		remember('blocks', null);

		expect(document.cookie).toBe('');
	});

	it('is sent to the panel only', () => {
		remember('blocks', 'wide');
		history.replaceState(null, '', '/about');

		expect(document.cookie).toBe('');
	});
});

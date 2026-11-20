import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/theme';

let uninstall: () => void = () => {};

function system(theme: 'light' | 'dark'): void {
	const unmatched = window.matchMedia;
	vi.stubGlobal('matchMedia', (media: string) => ({
		...unmatched(media),
		matches: theme === 'dark' && media === '(prefers-color-scheme: dark)',
	}));
}

function toggle(): HTMLButtonElement {
	return document.querySelector<HTMLButtonElement>('[data-theme-toggle]')!;
}

function theme(): string | undefined {
	return document.documentElement.dataset.theme;
}

function scheme(): string | null | undefined {
	return document.querySelector('meta[name="color-scheme"]')?.getAttribute('content');
}

beforeEach(() => {
	history.replaceState(null, '', '/panel');
	document.head.innerHTML = '<meta name="color-scheme" content="light dark">';
	document.body.innerHTML =
		'<button type="button" data-theme-toggle aria-pressed="false">Dark theme</button>';
});

afterEach(() => {
	uninstall();
	vi.unstubAllGlobals();
	delete document.documentElement.dataset.theme;
	document.cookie = 'cosray_panel=; path=/panel; max-age=0';
	history.replaceState(null, '', '/');
	document.body.innerHTML = '';
	document.head.innerHTML = '';
});

describe('theme toggle', () => {
	it('switches away from the system theme and remembers it for the next page', () => {
		system('light');
		uninstall = install();

		toggle().click();

		expect(theme()).toBe('dark');
		expect(document.cookie).toContain('cosray_panel=theme=dark');
		expect(toggle().getAttribute('aria-pressed')).toBe('true');
		expect(scheme()).toBe('dark');
	});

	it('follows the system again once the choice matches it', () => {
		system('dark');
		document.documentElement.dataset.theme = 'light';
		document.cookie = 'cosray_panel=theme=light; path=/panel';
		uninstall = install();

		expect(toggle().getAttribute('aria-pressed')).toBe('false');

		toggle().click();

		expect(theme()).toBeUndefined();
		expect(document.cookie).not.toContain('theme=');
		expect(toggle().getAttribute('aria-pressed')).toBe('true');
		expect(scheme()).toBe('light dark');
	});

	it('reports the system theme a page arrives in', () => {
		system('dark');
		uninstall = install();

		expect(toggle().getAttribute('aria-pressed')).toBe('true');
	});
});

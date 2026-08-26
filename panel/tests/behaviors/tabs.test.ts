import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/tabs';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	document.body.innerHTML = `
		<div class="cms-field" id="first">
			<button type="button" data-locale-tab="en" class="active">en</button>
			<button type="button" data-locale-tab="de">de</button>
			<div class="variant" data-locale="en"></div>
			<div class="variant" data-locale="de" hidden></div>
			<cosray-host></cosray-host>
		</div>
		<div class="cms-field" id="second">
			<button type="button" data-locale-tab="en" class="active">en</button>
			<button type="button" data-locale-tab="de">de</button>
			<div class="variant" data-locale="en"></div>
			<div class="variant" data-locale="de" hidden></div>
		</div>
	`;
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
});

function field(id: string): HTMLElement {
	const el = document.getElementById(id);

	if (!el) {
		throw new Error(`field ${id} missing`);
	}

	return el;
}

function activate(id: string, locale: string): void {
	field(id).querySelector<HTMLElement>(`[data-locale-tab="${locale}"]`)?.click();
}

describe('locale tabs', () => {
	it('toggles variant visibility and the active tab', () => {
		activate('first', 'de');

		const scope = field('first');

		expect(scope.querySelector('[data-locale="de"]')?.hasAttribute('hidden')).toBe(false);
		expect(scope.querySelector('[data-locale="en"]')?.hasAttribute('hidden')).toBe(true);
		expect(scope.querySelector('[data-locale-tab="de"]')?.classList.contains('active')).toBe(true);
		expect(scope.querySelector('[data-locale-tab="en"]')?.classList.contains('active')).toBe(false);
	});

	it('hands the editing locale to hosted elements', () => {
		activate('first', 'de');

		const host = field('first').querySelector('cosray-host') as HTMLElement & {
			locale?: string;
		};

		expect(host.locale).toBe('de');
	});

	it('scopes the switch to the field wrapper the tab sits in', () => {
		activate('first', 'de');

		const other = field('second');

		expect(other.querySelector('[data-locale="en"]')?.hasAttribute('hidden')).toBe(false);
		expect(other.querySelector('[data-locale-tab="en"]')?.classList.contains('active')).toBe(true);
	});
});

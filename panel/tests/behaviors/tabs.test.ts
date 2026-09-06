import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/tabs';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	document.body.innerHTML = `
		<div class="cms-field" data-locale-scope id="first">
			<button type="button" data-locale-tab="en" class="active">en</button>
			<button type="button" data-locale-tab="de">de</button>
			<div class="variant" data-locale="en"></div>
			<div class="variant" data-locale="de" hidden></div>
			<cosray-host></cosray-host>
		</div>
		<div class="cms-field" data-locale-scope id="second">
			<button type="button" data-locale-tab="en" class="active">en</button>
			<button type="button" data-locale-tab="de">de</button>
			<div class="variant" data-locale="en"></div>
			<div class="variant" data-locale="de" hidden></div>
		</div>
		<form data-content-locale-scope data-content-locale="en" id="global">
			<select data-content-locale-select data-editor-state>
				<option value="en" selected>English</option>
				<option value="de">Deutsch</option>
			</select>
			<div class="cms-field">
				<label data-locale-label-for="field-title" for="field-title-en">Title</label>
				<div class="variant" data-locale="en"><input id="field-title-en" /></div>
				<div class="variant" data-locale="de" hidden><input id="field-title-de" /></div>
				<cosray-host data-translated="true"></cosray-host>
				<cosray-host id="neutral"></cosray-host>
			</div>
		</form>
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

	it('switches every node-owned variant and translated host together', () => {
		const scope = field('global');
		const select = scope.querySelector<HTMLSelectElement>('[data-content-locale-select]')!;
		select.value = 'de';
		select.dispatchEvent(new Event('change', { bubbles: true }));

		expect(scope.dataset.contentLocale).toBe('de');
		expect(scope.querySelector('[data-locale="de"]')?.hasAttribute('hidden')).toBe(false);
		expect(scope.querySelector('[data-locale="en"]')?.hasAttribute('hidden')).toBe(true);
		expect(scope.querySelector('label')?.getAttribute('for')).toBe('field-title-de');
		expect(
			(scope.querySelector('[data-translated]') as HTMLElement & { locale?: string }).locale,
		).toBe('de');
		expect(
			(scope.querySelector('#neutral') as HTMLElement & { locale?: string }).locale,
		).toBeUndefined();
	});

	it('applies the current node locale to newly stamped rows', () => {
		const scope = field('global');
		const select = scope.querySelector<HTMLSelectElement>('[data-content-locale-select]')!;
		select.value = 'de';
		select.dispatchEvent(new Event('change', { bubbles: true }));
		const row = document.createElement('div');
		row.innerHTML = `
			<div class="variant" data-locale="en"></div>
			<div class="variant" data-locale="de" hidden></div>
			<cosray-host data-translated="true"></cosray-host>`;
		scope.append(row);
		row.dispatchEvent(new CustomEvent('repeater:stamp', { bubbles: true }));

		expect(row.querySelector('[data-locale="de"]')?.hasAttribute('hidden')).toBe(false);
		expect((row.querySelector('cosray-host') as HTMLElement & { locale?: string }).locale).toBe(
			'de',
		);
	});
});

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/content-locales';

let uninstall: (() => void) | null = null;

// The host contract the behavior hands the locale to.
if (!customElements.get('cosray-host')) {
	customElements.define(
		'cosray-host',
		class extends HTMLElement {
			locale = '';
		},
	);
}

function screen(selected = 'en'): void {
	const checked = (locale: string) => (locale === selected ? 'true' : 'false');
	const hidden = (locale: string) => (locale === selected ? '' : 'hidden');

	document.body.innerHTML = `
		<form data-content-locale-scope data-content-locale="${selected}" id="screen">
			<div data-content-locale-control data-editor-state role="radiogroup">
				<button type="button" data-content-locale-option="en" role="radio" aria-checked="${checked('en')}" tabindex="0">English</button>
				<button type="button" data-content-locale-option="de" role="radio" aria-checked="${checked('de')}" tabindex="-1">Deutsch</button>
			</div>
			<div class="cms-field">
				<label data-locale-label-for="field-title" for="field-title-${selected}">Title</label>
				<div class="variant" data-locale="en" ${hidden('en')}><input id="field-title-en" /></div>
				<div class="variant" data-locale="de" ${hidden('de')}><input id="field-title-de" /></div>
				<cosray-host data-translated="true"></cosray-host>
				<cosray-host id="neutral"></cosray-host>
				<dialog><div id="mirror"></div></dialog>
			</div>
		</form>
	`;
}

beforeEach(() => {
	screen();
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
	localStorage.clear();
});

function scope(): HTMLElement {
	const el = document.getElementById('screen');

	if (!el) {
		throw new Error('screen missing');
	}

	return el;
}

function choose(locale: string): void {
	scope().querySelector<HTMLButtonElement>(`[data-content-locale-option="${locale}"]`)?.click();
}

function request(from: Element, locale: string): void {
	from.dispatchEvent(
		new CustomEvent('content-locale:select', { bubbles: true, detail: { locale } }),
	);
}

describe('content language', () => {
	it('switches every variant and translated host of the screen together', () => {
		const control = scope().querySelector<HTMLElement>('[data-content-locale-control]')!;
		let changes = 0;
		control.addEventListener('content-locale:change', () => changes++);
		choose('de');

		expect(scope().dataset.contentLocale).toBe('de');
		expect(changes).toBe(1);
		expect(scope().querySelector('[data-locale="de"]')?.hasAttribute('hidden')).toBe(false);
		expect(scope().querySelector('[data-locale="en"]')?.hasAttribute('hidden')).toBe(true);
		expect(scope().querySelector('label')?.getAttribute('for')).toBe('field-title-de');
		expect(
			(scope().querySelector('[data-translated]') as HTMLElement & { locale?: string }).locale,
		).toBe('de');
		expect((scope().querySelector('#neutral') as HTMLElement & { locale?: string }).locale).toBe(
			'',
		);
	});

	it('supports arrow, Home, and End keys with one tab stop', () => {
		const english = scope().querySelector<HTMLButtonElement>('[data-content-locale-option="en"]')!;
		const german = scope().querySelector<HTMLButtonElement>('[data-content-locale-option="de"]')!;
		english.focus();
		english.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));

		expect(scope().dataset.contentLocale).toBe('de');
		expect(german.getAttribute('aria-checked')).toBe('true');
		expect(german.tabIndex).toBe(0);
		expect(english.tabIndex).toBe(-1);
		expect(document.activeElement).toBe(german);

		german.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true }));
		expect(scope().dataset.contentLocale).toBe('en');
		expect(document.activeElement).toBe(english);

		english.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));
		expect(scope().dataset.contentLocale).toBe('de');
		expect(document.activeElement).toBe(german);
	});

	it('keeps select controls for larger locale sets', () => {
		scope().querySelector('[data-content-locale-control]')?.remove();
		scope().insertAdjacentHTML(
			'afterbegin',
			'<select data-content-locale-control><option value="en">English</option><option value="de">Deutsch</option></select>',
		);
		const select = scope().querySelector<HTMLSelectElement>('[data-content-locale-control]')!;
		select.value = 'de';
		select.dispatchEvent(new Event('change', { bubbles: true }));

		expect(scope().dataset.contentLocale).toBe('de');
		expect(scope().querySelector('[data-locale="de"]')?.hasAttribute('hidden')).toBe(false);
	});

	it('keeps every copy of the selector in step', () => {
		const first = scope().querySelector<HTMLElement>('[data-content-locale-control]')!;
		const copy = first.cloneNode(true) as HTMLElement;
		scope().append(copy);
		copy.querySelector<HTMLButtonElement>('[data-content-locale-option="de"]')?.click();

		expect(scope().dataset.contentLocale).toBe('de');

		for (const control of [first, copy]) {
			const german = control.querySelector('[data-content-locale-option="de"]');
			expect(german?.getAttribute('aria-checked')).toBe('true');
			expect(german?.getAttribute('tabindex')).toBe('0');
		}
	});

	it('applies the current locale to newly stamped rows', () => {
		choose('de');
		const row = document.createElement('div');
		row.innerHTML = `
			<div class="variant" data-locale="en"></div>
			<div class="variant" data-locale="de" hidden></div>
			<cosray-host data-translated="true"></cosray-host>`;
		scope().append(row);
		row.dispatchEvent(new CustomEvent('repeater:stamp', { bubbles: true }));

		expect(row.querySelector('[data-locale="de"]')?.hasAttribute('hidden')).toBe(false);
		expect((row.querySelector('cosray-host') as HTMLElement & { locale?: string }).locale).toBe(
			'de',
		);
	});

	it('remembers the choice and opens the next screen in that language', () => {
		choose('de');

		expect(localStorage.getItem('cosray:content-locale')).toBe('de');

		uninstall?.();
		screen('en');
		let changes = 0;
		document.addEventListener('content-locale:change', () => changes++, { once: true });
		uninstall = install();

		expect(scope().dataset.contentLocale).toBe('de');
		expect(changes).toBe(1);
		expect(
			scope().querySelector('[data-content-locale-option="de"]')?.getAttribute('aria-checked'),
		).toBe('true');
		expect(scope().querySelector('[data-locale="de"]')?.hasAttribute('hidden')).toBe(false);
		expect(scope().querySelector('[data-locale="en"]')?.hasAttribute('hidden')).toBe(true);
		expect(scope().querySelector('label')?.getAttribute('for')).toBe('field-title-de');
	});

	it('ignores a remembered language the screen does not offer', () => {
		localStorage.setItem('cosray:content-locale', 'fr');
		uninstall?.();
		screen('en');
		uninstall = install();

		expect(scope().dataset.contentLocale).toBe('en');
		expect(scope().querySelector('[data-locale="en"]')?.hasAttribute('hidden')).toBe(false);
	});

	it('switches on a request from a control mirrored inside the screen', () => {
		request(document.getElementById('mirror')!, 'de');

		expect(scope().dataset.contentLocale).toBe('de');
		expect(
			scope().querySelector('[data-content-locale-option="de"]')?.getAttribute('aria-checked'),
		).toBe('true');
		expect(localStorage.getItem('cosray:content-locale')).toBe('de');
	});

	it("lets a dialog mounted outside every scope address the screen's one scope", () => {
		const dialog = document.createElement('dialog');
		document.body.append(dialog);
		request(dialog, 'de');

		expect(scope().dataset.contentLocale).toBe('de');

		const other = document.createElement('div');
		other.setAttribute('data-content-locale-scope', '');
		document.body.append(other);
		request(dialog, 'en');

		expect(scope().dataset.contentLocale).toBe('de');
	});
});

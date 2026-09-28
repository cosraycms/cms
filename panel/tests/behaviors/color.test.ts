import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install, normalize } from '../../src/behaviors/color';
import { install as installDirty } from '../../src/behaviors/dirty';

const stops: Array<() => void> = [];

beforeEach(() => {
	stops.push(install(), installDirty());
});

afterEach(() => {
	stops
		.splice(0)
		.reverse()
		.forEach((stop) => stop());
	document.body.innerHTML = '';
});

function control(value = '', field: Record<string, unknown> = {}): HTMLElement {
	const markup = execFileSync(
		'php',
		[resolve(dirname(fileURLToPath(import.meta.url)), '../../../tests/Fixtures/Panel/field.php')],
		{
			encoding: 'utf8',
			input: JSON.stringify({
				field: { name: 'accent', label: 'Accent', control: { name: 'color' }, ...field },
				data: { value: { zxx: value } },
				locales: [],
				defaultLocale: 'en',
			}),
		},
	);
	document.body.innerHTML = `<form id="node-editor-form">${markup}</form><p id="editor-dirty" hidden></p>`;

	return document.querySelector<HTMLElement>('[data-color]')!;
}

function text(): HTMLInputElement {
	return document.querySelector<HTMLInputElement>('[data-color-value]')!;
}

function picker(): HTMLInputElement {
	return document.querySelector<HTMLInputElement>('[data-color-picker]')!;
}

function swatch(): HTMLElement {
	return picker().parentElement!;
}

function fire(target: HTMLInputElement, type: 'input' | 'change', value?: string): void {
	if (value !== undefined) target.value = value;
	target.dispatchEvent(new Event(type, { bubbles: true }));
}

describe('normalize', () => {
	it('accepts the hex notations the server stores as #rrggbb', () => {
		expect(normalize('#E54231')).toBe('#e54231');
		expect(normalize(' abc ')).toBe('#aabbcc');
		expect(normalize('#fff')).toBe('#ffffff');
		expect(normalize('')).toBeNull();
		expect(normalize('red')).toBeNull();
		expect(normalize('#e54231ff')).toBeNull();
	});
});

describe('color field', () => {
	it('names only the text input, labelled by the field', () => {
		control('#e54231');

		expect(text().name).toBe('content[accent][value][zxx]');
		expect(document.querySelector('label')?.htmlFor).toBe(text().id);
		expect(picker().name).toBe('');
		expect(picker().getAttribute('aria-label')).toBe('Pick a color');
		expect(swatch().style.getPropertyValue('--color')).toBe('#e54231');
		expect(picker().value).toBe('#e54231');
	});

	it('shows an empty value as no color', () => {
		control('');

		expect(swatch().hasAttribute('data-empty')).toBe(true);
		expect(text().value).toBe('');
	});

	it('writes a picked color into the named input', () => {
		control('');

		fire(picker(), 'input', '#123456');

		expect(text().value).toBe('#123456');
		expect(swatch().hasAttribute('data-empty')).toBe(false);
		expect(swatch().style.getPropertyValue('--color')).toBe('#123456');
		expect(document.getElementById('editor-dirty')?.hidden).toBe(false);
	});

	it('follows typing and spells the value out on change', () => {
		control('#e54231');

		fire(text(), 'input', 'ABC');
		expect(swatch().style.getPropertyValue('--color')).toBe('#aabbcc');
		expect(picker().value).toBe('#aabbcc');
		expect(text().value).toBe('ABC');

		fire(text(), 'change');
		expect(text().value).toBe('#aabbcc');
	});

	it('leaves text that is no color for the server to reject', () => {
		control('#e54231');

		fire(text(), 'input', 'red');
		fire(text(), 'change');

		expect(text().value).toBe('red');
		expect(swatch().hasAttribute('data-empty')).toBe(true);
	});

	it('disables the picker on an immutable field', () => {
		control('#e54231', { immutable: true });

		expect(picker().disabled).toBe(true);
		expect(text().readOnly).toBe(true);
	});

	it('renders a stamped copy from its copied value', () => {
		control('#e54231');
		text().value = '#00ff00';

		document
			.querySelector('form')!
			.dispatchEvent(new CustomEvent('repeater:stamp', { bubbles: true }));

		expect(swatch().style.getPropertyValue('--color')).toBe('#00ff00');
	});
});

import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, describe, expect, it } from 'vitest';
import { nest } from '../../src/lib/form-json';

function render(value: boolean | null, options: Record<string, unknown> = {}): HTMLFormElement {
	const form = document.createElement('form');
	form.innerHTML = execFileSync(
		'php',
		[resolve(dirname(fileURLToPath(import.meta.url)), '../../../tests/Fixtures/Panel/field.php')],
		{
			encoding: 'utf8',
			input: JSON.stringify({
				field: {
					name: 'flag',
					label: 'Featured',
					control: { name: 'checkbox', props: {} },
					...options,
				},
				data: { value: { zxx: value } },
				locales: [{ id: 'en', title: 'English' }],
				defaultLocale: 'en',
			}),
		},
	);
	document.body.replaceChildren(form);
	return form;
}

function submitted(form: HTMLFormElement): unknown {
	return nest(new FormData(form).entries() as Iterable<[string, string]>);
}

afterEach(() => document.body.replaceChildren());

describe('checkbox field', () => {
	it('submits both states and keeps a stable accessible name when the status is clicked', () => {
		const form = render(true);
		const input = form.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
		const name = () =>
			document.getElementById(input.getAttribute('aria-labelledby')!)?.textContent?.trim();
		expect(name()).toBe('Featured');
		expect(submitted(form)).toEqual({ content: { flag: { value: { zxx: '1' } } } });

		input.closest('label')!.click();
		expect(input.checked).toBe(false);
		expect(name()).toBe('Featured');
		expect(submitted(form)).toEqual({ content: { flag: { value: { zxx: '' } } } });
	});

	it('accepts false as an answer to a required field', () => {
		const form = render(false, { required: true });
		expect(form.checkValidity()).toBe(true);
		expect(submitted(form)).toEqual({ content: { flag: { value: { zxx: '' } } } });
	});

	it('does not submit or change an immutable value', () => {
		const form = render(true, { immutable: true });
		const input = form.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
		input.click();
		expect(input.checked).toBe(true);
		expect(submitted(form)).toEqual({});
	});

	it('renders custom state labels as text', () => {
		const label = '<img src=x onerror=alert(1)>';
		const form = render(false, {
			control: { name: 'checkbox', props: { labels: { true: label, false: 'Disabled' } } },
		});
		expect(form.textContent).toContain(label);
		expect(form.querySelector('img')).toBeNull();
	});
});

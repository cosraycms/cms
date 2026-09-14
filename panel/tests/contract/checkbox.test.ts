import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, describe, expect, it } from 'vitest';
import { nest } from '../../src/lib/form-json';
import { install as conditions } from '../../src/behaviors/when';

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

	it.each<[boolean | null, string]>([
		[null, ''],
		[true, '1'],
		[false, '0'],
	])('restores a nullable value of %s without losing its state', (value, encoded) => {
		const form = render(value, {
			control: { name: 'checkbox', props: { nullable: true } },
		});
		const selected = form.querySelector<HTMLInputElement>('input:checked')!;
		expect(selected.value).toBe(encoded);
		expect(submitted(form)).toEqual({ content: { flag: { value: { zxx: encoded } } } });
	});

	it('lets editors choose and clear a nullable value without JavaScript', () => {
		const form = render(null, { control: { name: 'checkbox', props: { nullable: true } } });
		const group = form.querySelector('[role="radiogroup"]')!;
		expect(
			document.getElementById(group.getAttribute('aria-labelledby')!)?.textContent?.trim(),
		).toBe('Featured');
		const choices = Array.from(group.querySelectorAll<HTMLInputElement>('input'));
		expect(choices.map((input) => input.labels?.[0].textContent?.trim())).toEqual([
			'Not set',
			'Yes',
			'No',
		]);

		for (const encoded of ['1', '0', '']) {
			choices
				.find((input) => input.value === encoded)!
				.closest('label')!
				.click();
			expect(submitted(form)).toEqual({ content: { flag: { value: { zxx: encoded } } } });
			expect(choices.filter((input) => input.checked)).toHaveLength(1);
		}
	});

	it('requires a yes or no answer when the nullable field is required', () => {
		const form = render(null, {
			required: true,
			control: { name: 'checkbox', props: { nullable: true } },
		});
		expect(form.checkValidity()).toBe(false);
		form.querySelector<HTMLInputElement>('input[value="0"]')!.click();
		expect(form.checkValidity()).toBe(true);
		form.querySelector<HTMLInputElement>('input[value=""]')!.click();
		expect(submitted(form)).toEqual({ content: { flag: { value: { zxx: '0' } } } });
	});

	it('keeps an immutable null visible without submitting an empty value', () => {
		const form = render(null, {
			immutable: true,
			required: true,
			control: { name: 'checkbox', props: { nullable: true } },
		});
		form.querySelector<HTMLInputElement>('input[value="1"]')!.click();
		expect(form.querySelector<HTMLInputElement>('input:checked')?.value).toBe('');
		expect(form.checkValidity()).toBe(true);
		expect(submitted(form)).toEqual({});
	});

	it('preserves existing boolean conditional visibility for nullable controls', () => {
		const form = render(true, { control: { name: 'checkbox', props: { nullable: true } } });
		form.id = 'node-editor-form';
		const dependent = document.createElement('div');
		dependent.className = 'cms-field';
		dependent.dataset.when = JSON.stringify({ field: 'flag', op: 'eq', value: false });
		form.append(dependent);
		const teardown = conditions();
		try {
			expect(dependent.hidden).toBe(true);
			form.querySelector<HTMLInputElement>('input[value="0"]')!.click();
			expect(dependent.hidden).toBe(false);
			form.querySelector<HTMLInputElement>('input[value=""]')!.click();
			expect(dependent.hidden).toBe(false);
			form.querySelector<HTMLInputElement>('input[value="1"]')!.click();
			expect(dependent.hidden).toBe(true);
		} finally {
			teardown();
		}
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

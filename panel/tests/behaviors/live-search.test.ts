import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/live-search';

let uninstall: () => void;

function render(value = ''): HTMLInputElement {
	document.body.innerHTML = `
		<form method="get" action="/panel/collection/c" data-live-search>
			<input type="search" name="q" value="${value}" />
		</form>`;

	return document.querySelector<HTMLInputElement>('input[name="q"]')!;
}

function type(field: HTMLInputElement, value: string): void {
	field.value = value;
	field.setSelectionRange(value.length, value.length);
	field.dispatchEvent(new InputEvent('input', { bubbles: true }));
}

beforeEach(() => {
	vi.useFakeTimers();
	uninstall = install();
});

afterEach(() => {
	uninstall();
	vi.useRealTimers();
	vi.restoreAllMocks();
	document.body.innerHTML = '';
});

it('submits once typing pauses, not on every key', () => {
	const field = render();
	const submit = vi.spyOn(field.form!, 'requestSubmit').mockImplementation(() => {});

	type(field, 'Lu');
	vi.advanceTimersByTime(200);
	type(field, 'Luft');
	vi.advanceTimersByTime(299);
	expect(submit).not.toHaveBeenCalled();

	vi.advanceTimersByTime(1);
	expect(submit).toHaveBeenCalledOnce();
});

it('gives the re-rendered field its text, focus and caret back', () => {
	const field = render();
	vi.spyOn(field.form!, 'requestSubmit').mockImplementation(() => {
		field.form!.dispatchEvent(new Event('submit', { bubbles: true }));
	});

	type(field, 'Luft');
	vi.advanceTimersByTime(300);
	// Typed on while the request ran; the response knows only the first part.
	type(field, 'Luftfeuchte');

	const swapped = render('Luft');
	document.dispatchEvent(new CustomEvent('htmx:finally:swap'));

	expect(swapped.value).toBe('Luftfeuchte');
	expect(document.activeElement).toBe(swapped);
	expect(swapped.selectionStart).toBe('Luftfeuchte'.length);

	// The pending delay submits the newer text from the new form.
	const submit = vi.spyOn(swapped.form!, 'requestSubmit').mockImplementation(() => {});
	vi.advanceTimersByTime(300);
	expect(submit).toHaveBeenCalledOnce();
});

it('leaves forms without the marker alone', () => {
	document.body.innerHTML = '<form><input type="search" name="q" /></form>';
	const field = document.querySelector<HTMLInputElement>('input')!;
	const submit = vi.spyOn(field.form!, 'requestSubmit').mockImplementation(() => {});

	type(field, 'Luft');
	vi.advanceTimersByTime(1000);

	expect(submit).not.toHaveBeenCalled();
});

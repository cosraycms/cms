import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/autosubmit';

let uninstall: () => void;

function change(form: HTMLFormElement): ReturnType<typeof vi.spyOn> {
	const submit = vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});
	form.querySelector('select')!.dispatchEvent(new Event('change', { bubbles: true }));

	return submit;
}

beforeEach(() => {
	document.body.innerHTML = `
		<form id="marked" data-autosubmit><select><option>de</option><option>en</option></select></form>
		<form id="plain"><select><option>de</option><option>en</option></select></form>`;
	uninstall = install();
});

afterEach(() => {
	uninstall();
	vi.restoreAllMocks();
	document.body.innerHTML = '';
});

it('submits a marked form when one of its controls changes', () => {
	expect(change(document.querySelector<HTMLFormElement>('#marked')!)).toHaveBeenCalledOnce();
});

it('leaves other forms alone', () => {
	expect(change(document.querySelector<HTMLFormElement>('#plain')!)).not.toHaveBeenCalled();
});

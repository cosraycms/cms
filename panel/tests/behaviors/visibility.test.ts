import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/visibility';

let uninstall: () => void;

beforeEach(() => {
	document.body.innerHTML = `
		<form id="node-editor-form">
			<input id="online" type="checkbox" form="node-editor-visibility" name="online" value="1" />
			<input id="title" type="text" name="title" />
		</form>
		<form id="node-editor-visibility"></form>`;
	uninstall = install();
});

afterEach(() => {
	uninstall();
	vi.restoreAllMocks();
	document.body.innerHTML = '';
});

it('submits the visibility form when one of its switches changes, not the editor form', () => {
	const visibility = document.querySelector<HTMLFormElement>('#node-editor-visibility')!;
	const editor = document.querySelector<HTMLFormElement>('#node-editor-form')!;
	const submitVisibility = vi.spyOn(visibility, 'requestSubmit').mockImplementation(() => {});
	const submitEditor = vi.spyOn(editor, 'requestSubmit').mockImplementation(() => {});

	document.getElementById('online')!.dispatchEvent(new Event('change', { bubbles: true }));
	document.getElementById('title')!.dispatchEvent(new Event('change', { bubbles: true }));

	expect(submitVisibility).toHaveBeenCalledOnce();
	expect(submitEditor).not.toHaveBeenCalled();
});

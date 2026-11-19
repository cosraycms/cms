import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { install } from '../../src/behaviors/copy';

let uninstall: () => void;

function click(): void {
	document.querySelector<HTMLButtonElement>('button[data-copy]')!.click();
}

function toastText(): string | undefined {
	return document.querySelector('[data-toast], .cms-toasts-item')?.textContent ?? undefined;
}

beforeEach(() => {
	document.body.innerHTML = `
		<button type="button" data-copy="report"><span>Copy</span></button>
		<template id="report">Runtime
  PHP: 8.5</template>`;
	uninstall = install();
});

afterEach(() => {
	uninstall();
	vi.restoreAllMocks();
	vi.unstubAllGlobals();
	document.body.innerHTML = '';
});

it('copies the content of the template the button names', async () => {
	const writeText = vi.fn(async () => {});
	vi.stubGlobal('navigator', { clipboard: { writeText } });

	click();

	await vi.waitFor(() => expect(toastText()).toContain('common:copied'));
	expect(writeText).toHaveBeenCalledWith('Runtime\n  PHP: 8.5');
});

it('falls back to the copy command without a clipboard API', async () => {
	vi.stubGlobal('navigator', {});
	const exec = vi.fn(() => true);
	document.execCommand = exec;

	click();

	await vi.waitFor(() => expect(toastText()).toContain('common:copied'));
	expect(exec).toHaveBeenCalledWith('copy');
	expect(document.querySelector('textarea')).toBeNull();
});

it('says so when nothing could be copied', async () => {
	vi.stubGlobal('navigator', {});
	document.execCommand = vi.fn(() => false);

	click();

	await vi.waitFor(() => expect(toastText()).toContain('common:copy-failed'));
});

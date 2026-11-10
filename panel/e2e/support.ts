import { expect, type Locator, type Page } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const files = fileURLToPath(new URL('../../tests/Browser/.run/files/', import.meta.url));

export const COVER = 0;
export const GALLERY = 1;
export const STRICT = 2;
export const CLIP = 3;

export function file(name: string): string {
	return path.join(files, name);
}

export async function signIn(page: Page): Promise<void> {
	await page.goto('/panel/login');
	await page.getByLabel('Username or email').fill('tester');
	await page.getByLabel('Password').fill('browser-tests-password');
	await page.getByRole('button', { name: 'Sign in' }).click();
	await expect(page).toHaveURL(/\/panel\/?$/);
}

export async function openNode(page: Page, name: string): Promise<void> {
	await signIn(page);
	await page.goto(`/panel/node/browser${name}`);
	await expect(page.getByRole('button', { name: /^Save( draft)?$/ })).toBeVisible();

	// Tests share nodes, and edits a test leaves unsaved come back as an autosave.
	if ((await mine(page).getAttribute('data-state')) !== 'none') {
		await discardMine(page);
	}
}

/** The head's dot of the user's own edits. */
export function mine(page: Page): Locator {
	return page.locator('#editor-status-dots > [data-stage="mine"]');
}

/** Discards the user's own edits from the status tab and waits for the editor again. */
export async function discardMine(page: Page): Promise<void> {
	const reloaded = page.waitForResponse(
		(response) => response.request().method() === 'GET' && response.url().includes('/panel/node/'),
	);
	await page
		.locator('#editor-status-panel')
		.getByRole('button', { name: 'Discard' })
		.last()
		.click();
	await page.getByRole('dialog').getByRole('button', { name: 'Discard my changes' }).click();
	await reloaded;
	await expect(mine(page)).toHaveAttribute('data-state', 'none');
}

export function field(page: Page, index: number): Locator {
	return page.locator('.cms-media-field').nth(index);
}

export async function upload(target: Locator, ...names: string[]): Promise<void> {
	await target.locator('input[type=file]').first().setInputFiles(names.map(file));
}

export async function openDetails(target: Locator, page: Page): Promise<Locator> {
	await target.getByRole('button', { name: 'Edit' }).first().click();
	const dialog = page.locator('dialog[open]').last();
	await expect(dialog.getByRole('heading', { name: 'Image details' })).toBeVisible();

	return dialog;
}

export async function save(page: Page): Promise<void> {
	const saved = page.waitForResponse(
		(response) =>
			response.request().method() === 'POST' &&
			response.url().includes('/panel/node/') &&
			!response.url().endsWith('/autosave'),
	);
	await page.getByRole('button', { name: /^Save( draft)?$/ }).click();
	await saved;
}

/** Applies the edits to the main version, which the shared nodes already publish. */
export async function publish(page: Page): Promise<void> {
	const saved = page.waitForResponse(
		(response) =>
			response.request().method() === 'POST' &&
			response.url().includes('/panel/node/') &&
			!response.url().endsWith('/autosave'),
	);
	await page.getByRole('button', { name: 'Apply', exact: true }).click();
	await page
		.getByRole('menu')
		.getByRole('menuitem', { name: /^Apply draft/ })
		.first()
		.click();
	await saved;
}

/** The color of a served picture at a place, as fractions of its size. */
export async function colorAt(page: Page, image: Locator, x: number, y: number): Promise<number[]> {
	return image.evaluate(
		async (element: HTMLImageElement, [atX, atY]) => {
			await element.decode();
			const canvas = document.createElement('canvas');
			canvas.width = element.naturalWidth;
			canvas.height = element.naturalHeight;
			const context = canvas.getContext('2d', { willReadFrequently: true });
			context?.drawImage(element, 0, 0);
			const data = context?.getImageData(
				Math.floor(canvas.width * atX),
				Math.floor(canvas.height * atY),
				1,
				1,
			).data;

			return data ? [data[0], data[1], data[2]] : [];
		},
		[x, y],
	);
}

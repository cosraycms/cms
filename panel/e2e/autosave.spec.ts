import { expect, test } from '@playwright/test';
import { openNode } from './support';

test('edits survive a reload and come back on request', async ({ page }) => {
	let prompted = false;
	page.on('dialog', async (prompt) => {
		prompted = true;
		await prompt.accept();
	});
	await openNode(page, 'restore');
	const editor = page.url();
	const autosaved = page.waitForResponse(
		(response) => response.url().endsWith('/autosave') && response.ok(),
	);
	await page.getByLabel('Title').fill('Kept for later');
	await autosaved;

	await page.reload();
	expect(prompted).toBe(false);

	const question = page.getByRole('dialog', { name: 'Unsaved changes' });
	await expect(question.getByText(/were not saved\. Restore them\?/)).toBeVisible();
	await question.getByRole('button', { name: 'Restore' }).click();

	await expect(page.getByLabel('Title')).toHaveValue('Kept for later');
	await expect(page.locator('#editor-dirty')).toBeVisible();
	expect(page.url()).toBe(editor);

	await page.getByRole('button', { name: 'Save', exact: true }).click();
	await expect(page.locator('#editor-status')).toHaveText(/saved/i);
	await page.reload();
	await expect(page.getByLabel('Title')).toHaveValue('Kept for later');
	await expect(page.getByRole('dialog', { name: 'Unsaved changes' })).toHaveCount(0);
});

test('not restoring keeps the stored state', async ({ page }) => {
	await openNode(page, 'later');
	const autosaved = page.waitForResponse(
		(response) => response.url().endsWith('/autosave') && response.ok(),
	);
	await page.getByLabel('Title').fill('Maybe later');
	await autosaved;
	await page.reload();

	const question = page.getByRole('dialog', { name: 'Unsaved changes' });
	await question.getByRole('button', { name: 'Not now' }).click();

	await expect(question).toHaveCount(0);
	await expect(page.getByLabel('Title')).not.toHaveValue('Maybe later');
});
